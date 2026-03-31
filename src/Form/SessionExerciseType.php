<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Exercise;
use App\Entity\SessionExercise;
use App\Enum\SeriesType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<SessionExercise> */
class SessionExerciseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('exercise', EntityType::class, [
                'class' => Exercise::class,
                'label' => 'Ejercicio',
                'choice_label' => 'name',
                'query_builder' => function (\Doctrine\ORM\EntityRepository $repo) {
                    return $repo->createQueryBuilder('e')->orderBy('e.name', 'ASC');
                },
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('seriesType', EnumType::class, [
                'class' => SeriesType::class,
                'label' => 'Tipo de serie',
                'choice_label' => fn (SeriesType $type) => $type->label(),
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('targetSets', IntegerType::class, [
                'label' => 'Series objetivo',
                'attr' => [
                    'min' => 1,
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('targetReps', IntegerType::class, [
                'label' => 'Repeticiones objetivo',
                'required' => false,
                'attr' => [
                    'min' => 1,
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('targetWeight', NumberType::class, [
                'label' => 'Peso objetivo (kg)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'step' => '0.5',
                    'min' => 0,
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('superseriesGroup', IntegerType::class, [
                'label' => 'Grupo de superserie',
                'required' => false,
                'attr' => [
                    'min' => 1,
                    'placeholder' => 'Ej. 1 (para agrupar ejercicios)',
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('restSeconds', IntegerType::class, [
                'label' => 'Descanso entre series (s)',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Ej. 90',
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SessionExercise::class,
        ]);
    }
}
