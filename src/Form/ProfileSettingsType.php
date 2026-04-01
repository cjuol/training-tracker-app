<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

/** @extends AbstractType<User> */
class ProfileSettingsType extends AbstractType
{
    private const INPUT_CLASS = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('birthDate', DateType::class, [
                'label'    => 'Fecha de nacimiento',
                'widget'   => 'single_text',
                'required' => false,
                'constraints' => [
                    new LessThanOrEqual(
                        value: 'today',
                        message: 'La fecha de nacimiento no puede ser futura.'
                    ),
                ],
                'attr' => [
                    'class' => self::INPUT_CLASS,
                ],
            ])
            ->add('heightCm', NumberType::class, [
                'label'    => 'Altura (cm)',
                'required' => false,
                'scale'    => 1,
                'constraints' => [
                    new GreaterThan(value: 0, message: 'La altura debe ser mayor que 0.'),
                ],
                'attr' => [
                    'class'       => self::INPUT_CLASS,
                    'placeholder' => 'Ej. 175.0',
                    'step'        => '0.1',
                    'min'         => '50',
                    'max'         => '250',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
