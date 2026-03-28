<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AssignedMesocycle;
use App\Entity\BodyMeasurement;
use App\Entity\CoachAthlete;
use App\Entity\DailySteps;
use App\Entity\Exercise;
use App\Entity\Mesocycle;
use App\Entity\SessionExercise;
use App\Entity\SetLog;
use App\Entity\User;
use App\Entity\WorkoutLog;
use App\Entity\WorkoutSession;
use App\Enum\AssignmentStatus;
use App\Enum\MeasurementType;
use App\Enum\SeriesType;
use App\Enum\WorkoutStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comprehensive seed fixtures for development.
 *
 * Usage:
 *   docker compose run --rm php bin/console doctrine:fixtures:load --no-interaction
 *
 * WARNING: This PURGES the database before loading. Do NOT run in production.
 *
 * Credentials:
 *   Admin:    admin@example.com    / admin123
 *   Coach 1:  coach@example.com    / coach123
 *   Coach 2:  coach2@example.com   / coach123
 *   Athlete:  athlete@example.com  / athlete123
 *   Athlete2: athlete2@example.com / athlete123
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function getOrder(): int
    {
        return 0;
    }

    public function load(ObjectManager $manager): void
    {
        // =====================================================================
        // USERS
        // =====================================================================

        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setFirstName('Roberto');
        $admin->setLastName('Sanz');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $coach = new User();
        $coach->setEmail('coach@example.com');
        $coach->setFirstName('Carlos');
        $coach->setLastName('López');
        $coach->setRoles(['ROLE_ENTRENADOR']);
        $coach->setPassword($this->passwordHasher->hashPassword($coach, 'coach123'));
        $manager->persist($coach);

        $coach2 = new User();
        $coach2->setEmail('coach2@example.com');
        $coach2->setFirstName('Elena');
        $coach2->setLastName('Ruiz');
        $coach2->setRoles(['ROLE_ENTRENADOR']);
        $coach2->setPassword($this->passwordHasher->hashPassword($coach2, 'coach123'));
        $manager->persist($coach2);

        $ana = new User();
        $ana->setEmail('athlete@example.com');
        $ana->setFirstName('Ana');
        $ana->setLastName('Martínez');
        $ana->setRoles(['ROLE_ATLETA']);
        $ana->setPassword($this->passwordHasher->hashPassword($ana, 'athlete123'));
        $manager->persist($ana);

        $javier = new User();
        $javier->setEmail('athlete2@example.com');
        $javier->setFirstName('Javier');
        $javier->setLastName('Torres');
        $javier->setRoles(['ROLE_ATLETA']);
        $javier->setPassword($this->passwordHasher->hashPassword($javier, 'athlete123'));
        $manager->persist($javier);

        // =====================================================================
        // COACH-ATHLETE LINKS
        // =====================================================================

        $ca1 = new CoachAthlete();
        $ca1->setCoach($coach);
        $ca1->setAthlete($ana);
        $manager->persist($ca1);

        $ca2 = new CoachAthlete();
        $ca2->setCoach($coach);
        $ca2->setAthlete($javier);
        $manager->persist($ca2);

        $ca3 = new CoachAthlete();
        $ca3->setCoach($coach2);
        $ca3->setAthlete($ana);
        $manager->persist($ca3);

        // =====================================================================
        // EXERCISES (10, all created by Carlos)
        // =====================================================================

        $exBanca = new Exercise();
        $exBanca->setName('Press de Banca');
        $exBanca->setDescription('Ejercicio de empuje horizontal para pecho, hombros anteriores y tríceps.');
        $exBanca->setMeasurementType(MeasurementType::RepsWeight);
        $exBanca->setCreatedBy($coach);
        $manager->persist($exBanca);

        $exSentadilla = new Exercise();
        $exSentadilla->setName('Sentadilla Trasera');
        $exSentadilla->setDescription('Ejercicio fundamental de tren inferior con barra en trapecios.');
        $exSentadilla->setMeasurementType(MeasurementType::RepsWeight);
        $exSentadilla->setCreatedBy($coach);
        $manager->persist($exSentadilla);

        $exPesoMuerto = new Exercise();
        $exPesoMuerto->setName('Peso Muerto');
        $exPesoMuerto->setDescription('Patrón de bisagra de cadera. Trabaja cadena posterior completa.');
        $exPesoMuerto->setMeasurementType(MeasurementType::RepsWeight);
        $exPesoMuerto->setCreatedBy($coach);
        $manager->persist($exPesoMuerto);

        $exPressMilitar = new Exercise();
        $exPressMilitar->setName('Press Militar');
        $exPressMilitar->setDescription('Empuje vertical sobre la cabeza. Deltoides anterior y tríceps.');
        $exPressMilitar->setMeasurementType(MeasurementType::RepsWeight);
        $exPressMilitar->setCreatedBy($coach);
        $manager->persist($exPressMilitar);

        $exDominadas = new Exercise();
        $exDominadas->setName('Dominadas con Lastre');
        $exDominadas->setDescription('Jalón vertical con peso adicional en cinturón. Dorsal ancho y bíceps.');
        $exDominadas->setMeasurementType(MeasurementType::RepsWeight);
        $exDominadas->setCreatedBy($coach);
        $manager->persist($exDominadas);

        $exRemo = new Exercise();
        $exRemo->setName('Remo con Barra');
        $exRemo->setDescription('Tirón horizontal con barra. Romboides, trapecio medio y bíceps.');
        $exRemo->setMeasurementType(MeasurementType::RepsWeight);
        $exRemo->setCreatedBy($coach);
        $manager->persist($exRemo);

        $exCurl = new Exercise();
        $exCurl->setName('Curl de Bíceps');
        $exCurl->setDescription('Aislamiento de bíceps braquial con barra o mancuernas.');
        $exCurl->setMeasurementType(MeasurementType::RepsWeight);
        $exCurl->setCreatedBy($coach);
        $manager->persist($exCurl);

        $exTriceps = new Exercise();
        $exTriceps->setName('Extensión de Tríceps');
        $exTriceps->setDescription('Aislamiento de tríceps con polea o mancuerna sobre la cabeza.');
        $exTriceps->setMeasurementType(MeasurementType::RepsWeight);
        $exTriceps->setCreatedBy($coach);
        $manager->persist($exTriceps);

        $exCinta = new Exercise();
        $exCinta->setName('Carrera en Cinta');
        $exCinta->setDescription('Carrera continua o intervalos en cinta ergométrica.');
        $exCinta->setMeasurementType(MeasurementType::TimeDistance);
        $exCinta->setCreatedBy($coach);
        $manager->persist($exCinta);

        $exBicicleta = new Exercise();
        $exBicicleta->setName('Bicicleta HIIT');
        $exBicicleta->setDescription('Intervalos de alta intensidad en bicicleta estática.');
        $exBicicleta->setMeasurementType(MeasurementType::TimeKcal);
        $exBicicleta->setCreatedBy($coach);
        $manager->persist($exBicicleta);

        // =====================================================================
        // MESOCYCLE 1 — Fuerza Base (5 semanas)
        // =====================================================================

        $mesoFuerza = new Mesocycle();
        $mesoFuerza->setTitle('Fuerza Base — 5 semanas');
        $mesoFuerza->setDescription('Bloque de fuerza con progresión lineal en los levantamientos principales. Énfasis en 5×5 y series pesadas.');
        $mesoFuerza->setCoach($coach);
        $mesoFuerza->setDailyStepsTarget(8000);
        $manager->persist($mesoFuerza);

        // Fuerza Base — Sesión A Empuje (Lunes / orderIndex 1)
        $fbSesA = new WorkoutSession();
        $fbSesA->setName('Sesión A — Empuje');
        $fbSesA->setOrderIndex(1);
        $fbSesA->setDayOfWeek(1);
        $fbSesA->setMesocycle($mesoFuerza);
        $manager->persist($fbSesA);

        $fbA1 = new SessionExercise();
        $fbA1->setWorkoutSession($fbSesA);
        $fbA1->setExercise($exBanca);
        $fbA1->setSeriesType(SeriesType::NormalTs);
        $fbA1->setTargetSets(5);
        $fbA1->setTargetReps(5);
        $fbA1->setOrderIndex(1);
        $manager->persist($fbA1);

        $fbA2 = new SessionExercise();
        $fbA2->setWorkoutSession($fbSesA);
        $fbA2->setExercise($exPressMilitar);
        $fbA2->setSeriesType(SeriesType::NormalTs);
        $fbA2->setTargetSets(4);
        $fbA2->setTargetReps(8);
        $fbA2->setOrderIndex(2);
        $manager->persist($fbA2);

        $fbA3 = new SessionExercise();
        $fbA3->setWorkoutSession($fbSesA);
        $fbA3->setExercise($exTriceps);
        $fbA3->setSeriesType(SeriesType::NormalTs);
        $fbA3->setTargetSets(3);
        $fbA3->setTargetReps(12);
        $fbA3->setOrderIndex(3);
        $manager->persist($fbA3);

        // Fuerza Base — Sesión B Tirón (Miércoles / orderIndex 2)
        $fbSesB = new WorkoutSession();
        $fbSesB->setName('Sesión B — Tirón');
        $fbSesB->setOrderIndex(2);
        $fbSesB->setDayOfWeek(3);
        $fbSesB->setMesocycle($mesoFuerza);
        $manager->persist($fbSesB);

        $fbB1 = new SessionExercise();
        $fbB1->setWorkoutSession($fbSesB);
        $fbB1->setExercise($exPesoMuerto);
        $fbB1->setSeriesType(SeriesType::NormalTs);
        $fbB1->setTargetSets(4);
        $fbB1->setTargetReps(5);
        $fbB1->setOrderIndex(1);
        $manager->persist($fbB1);

        $fbB2 = new SessionExercise();
        $fbB2->setWorkoutSession($fbSesB);
        $fbB2->setExercise($exRemo);
        $fbB2->setSeriesType(SeriesType::NormalTs);
        $fbB2->setTargetSets(4);
        $fbB2->setTargetReps(8);
        $fbB2->setOrderIndex(2);
        $manager->persist($fbB2);

        $fbB3 = new SessionExercise();
        $fbB3->setWorkoutSession($fbSesB);
        $fbB3->setExercise($exDominadas);
        $fbB3->setSeriesType(SeriesType::NormalTs);
        $fbB3->setTargetSets(3);
        $fbB3->setTargetReps(6);
        $fbB3->setOrderIndex(3);
        $manager->persist($fbB3);

        // Fuerza Base — Sesión C Piernas (Viernes / orderIndex 3)
        $fbSesC = new WorkoutSession();
        $fbSesC->setName('Sesión C — Piernas');
        $fbSesC->setOrderIndex(3);
        $fbSesC->setDayOfWeek(5);
        $fbSesC->setMesocycle($mesoFuerza);
        $manager->persist($fbSesC);

        $fbC1 = new SessionExercise();
        $fbC1->setWorkoutSession($fbSesC);
        $fbC1->setExercise($exSentadilla);
        $fbC1->setSeriesType(SeriesType::NormalTs);
        $fbC1->setTargetSets(5);
        $fbC1->setTargetReps(5);
        $fbC1->setOrderIndex(1);
        $manager->persist($fbC1);

        $fbC2 = new SessionExercise();
        $fbC2->setWorkoutSession($fbSesC);
        $fbC2->setExercise($exCurl);
        $fbC2->setSeriesType(SeriesType::NormalTs);
        $fbC2->setTargetSets(3);
        $fbC2->setTargetReps(12);
        $fbC2->setOrderIndex(2);
        $manager->persist($fbC2);

        $fbC3 = new SessionExercise();
        $fbC3->setWorkoutSession($fbSesC);
        $fbC3->setExercise($exCinta);
        $fbC3->setSeriesType(SeriesType::Amrap);
        $fbC3->setTargetSets(1);
        $fbC3->setOrderIndex(3);
        $manager->persist($fbC3);

        // =====================================================================
        // MESOCYCLE 2 — Hipertrofia (6 semanas)
        // =====================================================================

        $mesoHiper = new Mesocycle();
        $mesoHiper->setTitle('Hipertrofia — 6 semanas');
        $mesoHiper->setDescription('Bloque orientado al volumen e hipertrofia. Rangos de repeticiones medios con alta densidad de trabajo.');
        $mesoHiper->setCoach($coach);
        $mesoHiper->setDailyStepsTarget(10000);
        $manager->persist($mesoHiper);

        // Hipertrofia — Sesión A Pecho y Hombros (Martes / orderIndex 1)
        $hpSesA = new WorkoutSession();
        $hpSesA->setName('Sesión A — Pecho y Hombros');
        $hpSesA->setOrderIndex(1);
        $hpSesA->setDayOfWeek(2);
        $hpSesA->setMesocycle($mesoHiper);
        $manager->persist($hpSesA);

        $hpA1 = new SessionExercise();
        $hpA1->setWorkoutSession($hpSesA);
        $hpA1->setExercise($exBanca);
        $hpA1->setSeriesType(SeriesType::NormalTs);
        $hpA1->setTargetSets(4);
        $hpA1->setTargetReps(10);
        $hpA1->setOrderIndex(1);
        $manager->persist($hpA1);

        $hpA2 = new SessionExercise();
        $hpA2->setWorkoutSession($hpSesA);
        $hpA2->setExercise($exPressMilitar);
        $hpA2->setSeriesType(SeriesType::NormalTs);
        $hpA2->setTargetSets(3);
        $hpA2->setTargetReps(12);
        $hpA2->setOrderIndex(2);
        $manager->persist($hpA2);

        $hpA3 = new SessionExercise();
        $hpA3->setWorkoutSession($hpSesA);
        $hpA3->setExercise($exTriceps);
        $hpA3->setSeriesType(SeriesType::NormalTs);
        $hpA3->setTargetSets(4);
        $hpA3->setTargetReps(15);
        $hpA3->setOrderIndex(3);
        $manager->persist($hpA3);

        // Hipertrofia — Sesión B Espalda y Bíceps (Jueves / orderIndex 2)
        $hpSesB = new WorkoutSession();
        $hpSesB->setName('Sesión B — Espalda y Bíceps');
        $hpSesB->setOrderIndex(2);
        $hpSesB->setDayOfWeek(4);
        $hpSesB->setMesocycle($mesoHiper);
        $manager->persist($hpSesB);

        $hpB1 = new SessionExercise();
        $hpB1->setWorkoutSession($hpSesB);
        $hpB1->setExercise($exDominadas);
        $hpB1->setSeriesType(SeriesType::NormalTs);
        $hpB1->setTargetSets(4);
        $hpB1->setTargetReps(8);
        $hpB1->setOrderIndex(1);
        $manager->persist($hpB1);

        $hpB2 = new SessionExercise();
        $hpB2->setWorkoutSession($hpSesB);
        $hpB2->setExercise($exRemo);
        $hpB2->setSeriesType(SeriesType::NormalTs);
        $hpB2->setTargetSets(4);
        $hpB2->setTargetReps(10);
        $hpB2->setOrderIndex(2);
        $manager->persist($hpB2);

        $hpB3 = new SessionExercise();
        $hpB3->setWorkoutSession($hpSesB);
        $hpB3->setExercise($exCurl);
        $hpB3->setSeriesType(SeriesType::NormalTs);
        $hpB3->setTargetSets(4);
        $hpB3->setTargetReps(12);
        $hpB3->setOrderIndex(3);
        $manager->persist($hpB3);

        // Hipertrofia — Sesión C Piernas y Cardio (Sábado / orderIndex 3)
        $hpSesC = new WorkoutSession();
        $hpSesC->setName('Sesión C — Piernas y Cardio');
        $hpSesC->setOrderIndex(3);
        $hpSesC->setDayOfWeek(6);
        $hpSesC->setMesocycle($mesoHiper);
        $manager->persist($hpSesC);

        $hpC1 = new SessionExercise();
        $hpC1->setWorkoutSession($hpSesC);
        $hpC1->setExercise($exSentadilla);
        $hpC1->setSeriesType(SeriesType::NormalTs);
        $hpC1->setTargetSets(4);
        $hpC1->setTargetReps(10);
        $hpC1->setOrderIndex(1);
        $manager->persist($hpC1);

        $hpC2 = new SessionExercise();
        $hpC2->setWorkoutSession($hpSesC);
        $hpC2->setExercise($exPesoMuerto);
        $hpC2->setSeriesType(SeriesType::NormalTs);
        $hpC2->setTargetSets(3);
        $hpC2->setTargetReps(8);
        $hpC2->setOrderIndex(2);
        $manager->persist($hpC2);

        $hpC3 = new SessionExercise();
        $hpC3->setWorkoutSession($hpSesC);
        $hpC3->setExercise($exBicicleta);
        $hpC3->setSeriesType(SeriesType::Amrap);
        $hpC3->setTargetSets(3);
        $hpC3->setOrderIndex(3);
        $manager->persist($hpC3);

        // =====================================================================
        // MESOCYCLE 3 — Acondicionamiento (4 semanas)
        // =====================================================================

        $mesoAcond = new Mesocycle();
        $mesoAcond->setTitle('Acondicionamiento — 4 semanas');
        $mesoAcond->setDescription('Bloque de acondicionamiento físico general. Volumen alto, pesos moderados, cardio integrado.');
        $mesoAcond->setCoach($coach);
        $mesoAcond->setDailyStepsTarget(12000);
        $manager->persist($mesoAcond);

        // Acondicionamiento — Sesión A Full Body A (Lunes / orderIndex 1)
        $acSesA = new WorkoutSession();
        $acSesA->setName('Sesión A — Full Body A');
        $acSesA->setOrderIndex(1);
        $acSesA->setDayOfWeek(1);
        $acSesA->setMesocycle($mesoAcond);
        $manager->persist($acSesA);

        $acA1 = new SessionExercise();
        $acA1->setWorkoutSession($acSesA);
        $acA1->setExercise($exSentadilla);
        $acA1->setSeriesType(SeriesType::NormalTs);
        $acA1->setTargetSets(3);
        $acA1->setTargetReps(15);
        $acA1->setOrderIndex(1);
        $manager->persist($acA1);

        $acA2 = new SessionExercise();
        $acA2->setWorkoutSession($acSesA);
        $acA2->setExercise($exBanca);
        $acA2->setSeriesType(SeriesType::NormalTs);
        $acA2->setTargetSets(3);
        $acA2->setTargetReps(15);
        $acA2->setOrderIndex(2);
        $manager->persist($acA2);

        $acA3 = new SessionExercise();
        $acA3->setWorkoutSession($acSesA);
        $acA3->setExercise($exCinta);
        $acA3->setSeriesType(SeriesType::NormalTs);
        $acA3->setTargetSets(2);
        $acA3->setOrderIndex(3);
        $manager->persist($acA3);

        // Acondicionamiento — Sesión B Full Body B (Miércoles / orderIndex 2)
        $acSesB = new WorkoutSession();
        $acSesB->setName('Sesión B — Full Body B');
        $acSesB->setOrderIndex(2);
        $acSesB->setDayOfWeek(3);
        $acSesB->setMesocycle($mesoAcond);
        $manager->persist($acSesB);

        $acB1 = new SessionExercise();
        $acB1->setWorkoutSession($acSesB);
        $acB1->setExercise($exPesoMuerto);
        $acB1->setSeriesType(SeriesType::NormalTs);
        $acB1->setTargetSets(3);
        $acB1->setTargetReps(12);
        $acB1->setOrderIndex(1);
        $manager->persist($acB1);

        $acB2 = new SessionExercise();
        $acB2->setWorkoutSession($acSesB);
        $acB2->setExercise($exPressMilitar);
        $acB2->setSeriesType(SeriesType::NormalTs);
        $acB2->setTargetSets(3);
        $acB2->setTargetReps(12);
        $acB2->setOrderIndex(2);
        $manager->persist($acB2);

        $acB3 = new SessionExercise();
        $acB3->setWorkoutSession($acSesB);
        $acB3->setExercise($exBicicleta);
        $acB3->setSeriesType(SeriesType::Amrap);
        $acB3->setTargetSets(3);
        $acB3->setOrderIndex(3);
        $manager->persist($acB3);

        // Acondicionamiento — Sesión C Accesorios (Viernes / orderIndex 3)
        $acSesC = new WorkoutSession();
        $acSesC->setName('Sesión C — Accesorios');
        $acSesC->setOrderIndex(3);
        $acSesC->setDayOfWeek(5);
        $acSesC->setMesocycle($mesoAcond);
        $manager->persist($acSesC);

        $acC1 = new SessionExercise();
        $acC1->setWorkoutSession($acSesC);
        $acC1->setExercise($exCurl);
        $acC1->setSeriesType(SeriesType::NormalTs);
        $acC1->setTargetSets(4);
        $acC1->setTargetReps(15);
        $acC1->setOrderIndex(1);
        $manager->persist($acC1);

        $acC2 = new SessionExercise();
        $acC2->setWorkoutSession($acSesC);
        $acC2->setExercise($exTriceps);
        $acC2->setSeriesType(SeriesType::NormalTs);
        $acC2->setTargetSets(4);
        $acC2->setTargetReps(15);
        $acC2->setOrderIndex(2);
        $manager->persist($acC2);

        $acC3 = new SessionExercise();
        $acC3->setWorkoutSession($acSesC);
        $acC3->setExercise($exDominadas);
        $acC3->setSeriesType(SeriesType::NormalTs);
        $acC3->setTargetSets(3);
        $acC3->setTargetReps(10);
        $acC3->setOrderIndex(3);
        $manager->persist($acC3);

        // =====================================================================
        // ASSIGNED MESOCYCLES
        // =====================================================================

        // 1) Fuerza Base → Ana, by Carlos, start=42 days ago, Completed
        $assignFuerzaAna = new AssignedMesocycle();
        $assignFuerzaAna->setMesocycle($mesoFuerza);
        $assignFuerzaAna->setAthlete($ana);
        $assignFuerzaAna->setAssignedBy($coach);
        $assignFuerzaAna->setStartDate(new \DateTimeImmutable('-42 days'));
        $assignFuerzaAna->setEndDate(new \DateTimeImmutable('-8 days'));
        $assignFuerzaAna->setStatus(AssignmentStatus::Completed);
        $manager->persist($assignFuerzaAna);

        // 2) Hipertrofia → Ana, by Carlos, start=28 days ago, Active
        $assignHiperAna = new AssignedMesocycle();
        $assignHiperAna->setMesocycle($mesoHiper);
        $assignHiperAna->setAthlete($ana);
        $assignHiperAna->setAssignedBy($coach);
        $assignHiperAna->setStartDate(new \DateTimeImmutable('-28 days'));
        $assignHiperAna->setStatus(AssignmentStatus::Active);
        $manager->persist($assignHiperAna);

        // 3) Fuerza Base → Javier, by Carlos, start=28 days ago, Active
        $assignFuerzaJavier = new AssignedMesocycle();
        $assignFuerzaJavier->setMesocycle($mesoFuerza);
        $assignFuerzaJavier->setAthlete($javier);
        $assignFuerzaJavier->setAssignedBy($coach);
        $assignFuerzaJavier->setStartDate(new \DateTimeImmutable('-28 days'));
        $assignFuerzaJavier->setStatus(AssignmentStatus::Active);
        $manager->persist($assignFuerzaJavier);

        // 4) Acondicionamiento → Ana, by Elena, start=14 days ago, Paused (Abandoned maps to Paused)
        $assignAcondAna = new AssignedMesocycle();
        $assignAcondAna->setMesocycle($mesoAcond);
        $assignAcondAna->setAthlete($ana);
        $assignAcondAna->setAssignedBy($coach2);
        $assignAcondAna->setStartDate(new \DateTimeImmutable('-14 days'));
        $assignAcondAna->setStatus(AssignmentStatus::Paused);
        $manager->persist($assignAcondAna);

        $manager->flush();

        // =====================================================================
        // WORKOUT LOGS — Ana on Fuerza Base (12 completed logs, last 5 weeks)
        // Sessions cycle: A (Mon), B (Wed), C (Fri)
        //
        // Week 1: day -35 (A), -33 (B), -31 (C)
        // Week 2: day -28 (A), -26 (B), -24 (C)
        // Week 3: day -21 (A), -19 (B)  [skip C for realism]
        // Week 4: day -14 (A), -12 (B), -10 (C)
        // Week 5: day -7  (A), -5  (B)  [skip C for realism]
        // Total = 12 completed logs
        // =====================================================================

        // Map session exercises per session for easy lookup
        $fbSesAExercises = [$fbA1, $fbA2, $fbA3];
        $fbSesBExercises = [$fbB1, $fbB2, $fbB3];
        $fbSesCExercises = [$fbC1, $fbC2, $fbC3];

        // Weight progression: +2.5 kg every 2 sessions (index-based)
        // progressionBonus($logIndex) = floor($logIndex / 2) * 2.5
        $progressBonus = static fn (int $logIndex): float => (float) (intdiv($logIndex, 2)) * 2.5;

        // Completed Ana logs definition: [daysAgo, session, sessionExercises]
        $anaFuerzaLogs = [
            // Week 1
            [35, $fbSesA, $fbSesAExercises, 0],
            [33, $fbSesB, $fbSesBExercises, 0],
            [31, $fbSesC, $fbSesCExercises, 0],
            // Week 2
            [28, $fbSesA, $fbSesAExercises, 1],
            [26, $fbSesB, $fbSesBExercises, 1],
            [24, $fbSesC, $fbSesCExercises, 1],
            // Week 3 (skip C)
            [21, $fbSesA, $fbSesAExercises, 2],
            [19, $fbSesB, $fbSesBExercises, 2],
            // Week 4
            [14, $fbSesA, $fbSesAExercises, 3],
            [12, $fbSesB, $fbSesBExercises, 3],
            [10, $fbSesC, $fbSesCExercises, 3],
            // Week 5 (skip C)
            [7,  $fbSesA, $fbSesAExercises, 4],
        ];

        // Helper: create a completed WorkoutLog and its SetLogs for Ana
        $createAnaLog = function (
            int $daysAgo,
            WorkoutSession $session,
            array $sessionExercises,
            int $logIndex
        ) use ($manager, $ana, $assignFuerzaAna, $progressBonus, $fbA1, $fbA2, $fbA3, $fbB1, $fbB2, $fbB3, $fbC1, $fbC2, $fbC3): void {
            $bonus = $progressBonus($logIndex);
            $startDt = (new \DateTimeImmutable("-{$daysAgo} days"))->setTime(18, 0, 0);
            $endDt   = $startDt->modify('+55 minutes');

            $log = new WorkoutLog();
            // Override the auto-set startTime via reflection (no setter exists)
            $ref = new \ReflectionProperty(WorkoutLog::class, 'startTime');
            $ref->setAccessible(true);
            $ref->setValue($log, $startDt);

            $log->setAthlete($ana);
            $log->setWorkoutSession($session);
            $log->setAssignedMesocycle($assignFuerzaAna);
            $log->setStatus(WorkoutStatus::Completed);
            $log->setEndTime($endDt);
            $manager->persist($log);

            foreach ($sessionExercises as $se) {
                /** @var SessionExercise $se */
                $targetSets = $se->getTargetSets();
                $targetReps = $se->getTargetReps();

                for ($setNum = 1; $setNum <= $targetSets; $setNum++) {
                    $setLog = new SetLog();
                    $setLog->setWorkoutLog($log);
                    $setLog->setSessionExercise($se);
                    $setLog->setSetNumber($setNum);

                    // Determine weights/data by exercise identity
                    if ($se === $fbA1) {
                        // Press de Banca: base 72.5, +bonus
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(72.5 + $bonus);
                        $setLog->setRir($setNum <= 3 ? 2 : 1);
                    } elseif ($se === $fbA2) {
                        // Press Militar: base 52.5
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(52.5 + $bonus);
                        $setLog->setRir(2);
                    } elseif ($se === $fbA3) {
                        // Extensión de Tríceps: base 32.5
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(32.5 + $bonus);
                        $setLog->setRir(1);
                    } elseif ($se === $fbB1) {
                        // Peso Muerto: base 100
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(100.0 + $bonus);
                        $setLog->setRir(1);
                    } elseif ($se === $fbB2) {
                        // Remo con Barra: base 70
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(70.0 + $bonus);
                        $setLog->setRir(2);
                    } elseif ($se === $fbB3) {
                        // Dominadas con Lastre: base 12.5
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(12.5 + $bonus);
                        $setLog->setRir(2);
                    } elseif ($se === $fbC1) {
                        // Sentadilla Trasera: base 82.5
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(82.5 + $bonus);
                        $setLog->setRir(1);
                    } elseif ($se === $fbC2) {
                        // Curl de Bíceps: base 25
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(25.0 + $bonus);
                        $setLog->setRir(1);
                    } elseif ($se === $fbC3) {
                        // Carrera en Cinta: cardio (TimeDistance)
                        $setLog->setTimeDuration(1800);
                        $setLog->setDistance(5.0);
                    }

                    $manager->persist($setLog);
                }
            }
        };

        foreach ($anaFuerzaLogs as [$daysAgo, $session, $sessionExercises, $logIndex]) {
            $createAnaLog($daysAgo, $session, $sessionExercises, $logIndex);
        }

        // =====================================================================
        // WORKOUT LOG — Ana InProgress today on Hipertrofia Sesión A
        // (2 sets logged for Press de Banca only)
        // =====================================================================

        $anaInProgressLog = new WorkoutLog();
        // startTime set via reflection to 18:00 today
        $inProgressStart = (new \DateTimeImmutable('today'))->setTime(18, 0, 0);
        $refProp = new \ReflectionProperty(WorkoutLog::class, 'startTime');
        $refProp->setAccessible(true);
        $refProp->setValue($anaInProgressLog, $inProgressStart);

        $anaInProgressLog->setAthlete($ana);
        $anaInProgressLog->setWorkoutSession($hpSesA);
        $anaInProgressLog->setAssignedMesocycle($assignHiperAna);
        $anaInProgressLog->setStatus(WorkoutStatus::InProgress);
        $anaInProgressLog->setCurrentExerciseId($hpA1->getId());
        $manager->persist($anaInProgressLog);

        $inProgressSet1 = new SetLog();
        $inProgressSet1->setWorkoutLog($anaInProgressLog);
        $inProgressSet1->setSessionExercise($hpA1);
        $inProgressSet1->setSetNumber(1);
        $inProgressSet1->setReps(10);
        $inProgressSet1->setWeight(75.0);
        $inProgressSet1->setRir(2);
        $manager->persist($inProgressSet1);

        $inProgressSet2 = new SetLog();
        $inProgressSet2->setWorkoutLog($anaInProgressLog);
        $inProgressSet2->setSessionExercise($hpA1);
        $inProgressSet2->setSetNumber(2);
        $inProgressSet2->setReps(10);
        $inProgressSet2->setWeight(75.0);
        $inProgressSet2->setRir(2);
        $manager->persist($inProgressSet2);

        // =====================================================================
        // WORKOUT LOGS — Javier on Fuerza Base (4 completed, last 2 weeks)
        // Week 1: day -14 (A), -12 (B)
        // Week 2: day -7  (A), -5  (B)
        // =====================================================================

        $javierFuerzaLogs = [
            [14, $fbSesA, $fbSesAExercises, 0],
            [12, $fbSesB, $fbSesBExercises, 0],
            [7,  $fbSesA, $fbSesAExercises, 1],
            [5,  $fbSesB, $fbSesBExercises, 1],
        ];

        $createJavierLog = function (
            int $daysAgo,
            WorkoutSession $session,
            array $sessionExercises,
            int $logIndex
        ) use ($manager, $javier, $assignFuerzaJavier, $progressBonus, $fbA1, $fbA2, $fbA3, $fbB1, $fbB2, $fbB3, $fbC1, $fbC2, $fbC3): void {
            $bonus = $progressBonus($logIndex);
            $startDt = (new \DateTimeImmutable("-{$daysAgo} days"))->setTime(19, 30, 0);
            $endDt   = $startDt->modify('+60 minutes');

            $log = new WorkoutLog();
            $ref = new \ReflectionProperty(WorkoutLog::class, 'startTime');
            $ref->setAccessible(true);
            $ref->setValue($log, $startDt);

            $log->setAthlete($javier);
            $log->setWorkoutSession($session);
            $log->setAssignedMesocycle($assignFuerzaJavier);
            $log->setStatus(WorkoutStatus::Completed);
            $log->setEndTime($endDt);
            $manager->persist($log);

            foreach ($sessionExercises as $se) {
                /** @var SessionExercise $se */
                $targetSets = $se->getTargetSets();
                $targetReps = $se->getTargetReps();

                for ($setNum = 1; $setNum <= $targetSets; $setNum++) {
                    $setLog = new SetLog();
                    $setLog->setWorkoutLog($log);
                    $setLog->setSessionExercise($se);
                    $setLog->setSetNumber($setNum);

                    if ($se === $fbA1) {
                        // Press de Banca: Javier heavier, base 90
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(90.0 + $bonus);
                        $setLog->setRir(2);
                    } elseif ($se === $fbA2) {
                        // Press Militar: base 65
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(65.0 + $bonus);
                        $setLog->setRir(2);
                    } elseif ($se === $fbA3) {
                        // Extensión de Tríceps: base 40
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(40.0 + $bonus);
                        $setLog->setRir(1);
                    } elseif ($se === $fbB1) {
                        // Peso Muerto: base 130
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(130.0 + $bonus);
                        $setLog->setRir(1);
                    } elseif ($se === $fbB2) {
                        // Remo con Barra: base 85
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(85.0 + $bonus);
                        $setLog->setRir(2);
                    } elseif ($se === $fbB3) {
                        // Dominadas con Lastre: base 20
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(20.0 + $bonus);
                        $setLog->setRir(2);
                    } elseif ($se === $fbC1) {
                        // Sentadilla Trasera: base 100
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(100.0 + $bonus);
                        $setLog->setRir(1);
                    } elseif ($se === $fbC2) {
                        // Curl de Bíceps: base 30
                        $setLog->setReps($targetReps);
                        $setLog->setWeight(30.0 + $bonus);
                        $setLog->setRir(1);
                    } elseif ($se === $fbC3) {
                        $setLog->setTimeDuration(1800);
                        $setLog->setDistance(5.0);
                    }

                    $manager->persist($setLog);
                }
            }
        };

        foreach ($javierFuerzaLogs as [$daysAgo, $session, $sessionExercises, $logIndex]) {
            $createJavierLog($daysAgo, $session, $sessionExercises, $logIndex);
        }

        $manager->flush();

        // =====================================================================
        // BODY MEASUREMENTS — Ana (5 entries, monthly for 4 months)
        // =====================================================================

        $anaMeasurements = [
            [120, 72.00, 90.00, 72.00, 96.00, 32.00, 'Inicio del seguimiento de composición corporal.'],
            [90,  71.50, 89.50, 71.00, 95.50, 32.50, 'Mes 2 — tendencia positiva en composición.'],
            [60,  70.80, 89.00, 70.00, 95.00, 33.00, 'Mes 3 — pérdida de grasa y ganancia muscular leve.'],
            [30,  70.20, 88.50, 69.50, 94.50, 33.50, 'Mes 4 — progresión continua. Masa muscular en brazos en aumento.'],
            [0,   69.80, 88.00, 69.00, 94.00, 34.00, 'Medición actual — excelente evolución en 4 meses.'],
        ];

        foreach ($anaMeasurements as [$daysAgo, $weight, $chest, $waist, $hips, $arms, $notes]) {
            $m = new BodyMeasurement();
            $m->setAthlete($ana);
            $m->setMeasurementDate(new \DateTimeImmutable("-{$daysAgo} days"));
            $m->setWeightKg($weight);
            $m->setChestCm($chest);
            $m->setWaistCm($waist);
            $m->setHipsCm($hips);
            $m->setArmsCm($arms);
            $m->setNotes($notes);
            $manager->persist($m);
        }

        // =====================================================================
        // BODY MEASUREMENTS — Javier (3 entries)
        // =====================================================================

        $javierMeasurements = [
            [60, 82.00, 100.00, 88.00, 102.00, 38.00, 'Inicio del seguimiento.'],
            [30, 82.50, 100.50, 87.00, 102.00, 38.50, 'Mes 2 — leve ganancia de masa muscular.'],
            [0,  83.00, 101.00, 86.50, 102.00, 39.00, 'Medición actual — buen progreso en fuerza e hipertrofia.'],
        ];

        foreach ($javierMeasurements as [$daysAgo, $weight, $chest, $waist, $hips, $arms, $notes]) {
            $m = new BodyMeasurement();
            $m->setAthlete($javier);
            $m->setMeasurementDate(new \DateTimeImmutable("-{$daysAgo} days"));
            $m->setWeightKg($weight);
            $m->setChestCm($chest);
            $m->setWaistCm($waist);
            $m->setHipsCm($hips);
            $m->setArmsCm($arms);
            $m->setNotes($notes);
            $manager->persist($m);
        }

        // =====================================================================
        // DAILY STEPS — Ana (45 days)
        // =====================================================================

        for ($i = 0; $i <= 44; $i++) {
            $date = new \DateTimeImmutable("-{$i} days");
            $dow  = (int) $date->format('N'); // 1=Mon … 7=Sun
            $steps = ($dow >= 6)
                ? random_int(4000, 6000)
                : random_int(7000, 10500);

            $ds = new DailySteps();
            $ds->setUser($ana);
            $ds->setDate($date);
            $ds->setSteps($steps);
            $manager->persist($ds);
        }

        // =====================================================================
        // DAILY STEPS — Javier (30 days)
        // =====================================================================

        for ($i = 0; $i <= 29; $i++) {
            $date = new \DateTimeImmutable("-{$i} days");
            $dow  = (int) $date->format('N');
            $steps = ($dow >= 6)
                ? random_int(5000, 7000)
                : random_int(8000, 11000);

            $ds = new DailySteps();
            $ds->setUser($javier);
            $ds->setDate($date);
            $ds->setSteps($steps);
            $manager->persist($ds);
        }

        $manager->flush();
    }
}
