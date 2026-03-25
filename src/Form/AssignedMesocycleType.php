<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AssignedMesocycle;
use App\Entity\CoachAthlete;
use App\Entity\Mesocycle;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<AssignedMesocycle> */
class AssignedMesocycleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $coach */
        $coach = $options['coach'];

        $builder
            ->add('athlete', EntityType::class, [
                'class' => User::class,
                'label' => 'Atleta',
                'placeholder' => 'Selecciona un atleta…',
                'query_builder' => function (EntityRepository $er) use ($coach): QueryBuilder {
                    return $er->createQueryBuilder('u')
                        ->join(CoachAthlete::class, 'ca', 'WITH', 'ca.athlete = u AND ca.coach = :coach')
                        ->setParameter('coach', $coach)
                        ->orderBy('u.lastName', 'ASC')
                        ->addOrderBy('u.firstName', 'ASC');
                },
                'choice_label' => fn (User $u) => $u->getFullName().' ('.$u->getEmail().')',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('mesocycle', EntityType::class, [
                'class' => Mesocycle::class,
                'label' => 'Mesociclo',
                'placeholder' => 'Selecciona un mesociclo…',
                'query_builder' => function (EntityRepository $er) use ($coach): QueryBuilder {
                    return $er->createQueryBuilder('m')
                        ->andWhere('m.coach = :coach')
                        ->setParameter('coach', $coach)
                        ->orderBy('m.title', 'ASC');
                },
                'choice_label' => 'title',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Fecha de inicio',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Fecha de fin',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AssignedMesocycle::class,
        ]);

        $resolver->setRequired('coach');
        $resolver->setAllowedTypes('coach', User::class);
    }
}
