<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\WorkoutSession;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<WorkoutSession> */
class WorkoutSessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre de la sesión',
                'attr' => [
                    'placeholder' => 'Ej. Día A — Empuje',
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('orderIndex', IntegerType::class, [
                'label' => 'Orden',
                'attr' => [
                    'min' => 1,
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('dayOfWeek', ChoiceType::class, [
                'label' => 'Día de la semana',
                'required' => false,
                'placeholder' => '— Sin día asignado —',
                'choices' => [
                    'Lunes' => 1,
                    'Martes' => 2,
                    'Miércoles' => 3,
                    'Jueves' => 4,
                    'Viernes' => 5,
                    'Sábado' => 6,
                    'Domingo' => 7,
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkoutSession::class,
        ]);
    }
}
