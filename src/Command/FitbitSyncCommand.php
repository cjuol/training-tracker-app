<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\FitbitRateLimitException;
use App\Exception\FitbitTokenRevokedException;
use App\Repository\UserRepository;
use App\Service\Fitbit\FitbitSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:fitbit:sync', description: 'Sync Fitbit data for connected athletes')]
class FitbitSyncCommand extends Command
{
    public function __construct(
        private readonly FitbitSyncService $syncService,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Sync only this user ID')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Number of days back to sync', 7)
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Start date (Y-m-d)')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'End date (Y-m-d), defaults to today');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $to = $input->getOption('to')
            ? new \DateTimeImmutable($input->getOption('to'))
            : new \DateTimeImmutable('today');

        $from = $input->getOption('from')
            ? new \DateTimeImmutable($input->getOption('from'))
            : $to->modify('-' . ((int) $input->getOption('days') - 1) . ' days');

        if ($userId = $input->getOption('user')) {
            $users = array_filter([$this->userRepository->find((int) $userId)]);
        } else {
            $users = $this->userRepository->findUsersWithValidFitbitToken();
        }

        $io->info(sprintf('Syncing %d user(s) from %s to %s', count($users), $from->format('Y-m-d'), $to->format('Y-m-d')));

        foreach ($users as $user) {
            $io->section('User: ' . $user->getEmail());
            $current = $from;
            while ($current <= $to) {
                try {
                    $this->syncService->syncUser($user, $current);
                    $io->writeln('  ✓ ' . $current->format('Y-m-d'));
                } catch (FitbitRateLimitException) {
                    $io->warning('Rate limit hit for ' . $user->getEmail() . '. Stopping this user.');
                    break;
                } catch (FitbitTokenRevokedException) {
                    $io->warning('Token revoked for ' . $user->getEmail() . '. Skipping.');
                    break;
                } catch (\Throwable $e) {
                    $io->error($user->getEmail() . ' on ' . $current->format('Y-m-d') . ': ' . $e->getMessage());
                }
                $current = $current->modify('+1 day');
            }
        }

        $io->success('Fitbit sync complete.');

        return Command::SUCCESS;
    }
}
