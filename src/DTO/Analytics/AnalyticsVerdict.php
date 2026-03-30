<?php

declare(strict_types=1);

namespace App\DTO\Analytics;

final readonly class AnalyticsVerdict
{
    public function __construct(
        public string $module,
        /** Possible values: STABLE | CAUTION | ALERT | GOOD | POOR | TRENDING_UP | TRENDING_DOWN | FLAT | INSUFFICIENT_DATA */
        public string $verdict,
        /** 0.0–100.0, or -1.0 when INSUFFICIENT_DATA */
        public float $score,
        /** @var string[] */
        public array $insights,
        /** @var float[] */
        public array $outliers,
        public \DateTimeImmutable $computedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'module'     => $this->module,
            'verdict'    => $this->verdict,
            'score'      => $this->score,
            'insights'   => $this->insights,
            'outliers'   => $this->outliers,
            'computedAt' => $this->computedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    public static function insufficientData(string $module): self
    {
        return new self(
            module: $module,
            verdict: 'INSUFFICIENT_DATA',
            score: -1.0,
            insights: ['No hay suficientes datos (mínimo 5 puntos).'],
            outliers: [],
            computedAt: new \DateTimeImmutable(),
        );
    }
}
