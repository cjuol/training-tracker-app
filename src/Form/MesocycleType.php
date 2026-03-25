<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Mesocycle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Mesocycle> */
class MesocycleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Título',
                'attr' => [
                    'placeholder' => 'Ej. Mesociclo de Fuerza — 4 semanas',
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Descripción opcional del mesociclo...',
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ])
            ->add('dailyStepsTarget', IntegerType::class, [
                'label' => 'Objetivo diario de pasos',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Ej. 8000',
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
                'help' => 'Opcional. Deja en blanco si no aplica.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mesocycle::class,
        ]);
    }
}
