<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\DailySteps;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class DailyStepsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'label' => 'Fecha',
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm'],
            ])
            ->add('steps', IntegerType::class, [
                'label' => 'Pasos',
                'constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual(0),
                ],
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Ej. 8000',
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DailySteps::class]);
    }
}
