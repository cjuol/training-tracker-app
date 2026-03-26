<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BodyMeasurement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

/** @extends AbstractType<BodyMeasurement> */
class BodyMeasurementType extends AbstractType
{
    private const INPUT_CLASS = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('measurementDate', DateType::class, [
                'label' => 'Fecha de medición',
                'widget' => 'single_text',
                'required' => true,
                'constraints' => [
                    new NotNull(message: 'La fecha es obligatoria.'),
                    new LessThanOrEqual(
                        value: 'today',
                        message: 'La fecha no puede ser futura.'
                    ),
                ],
                'attr' => [
                    'class' => self::INPUT_CLASS,
                ],
            ])
            ->add('weightKg', NumberType::class, [
                'label' => 'Peso (kg)',
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new GreaterThan(value: 0, message: 'El peso debe ser mayor que 0.'),
                    new LessThan(value: 1000, message: 'El valor no puede superar 999.99.'),
                ],
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej. 75.50',
                    'step' => '0.01',
                ],
            ])
            ->add('chestCm', NumberType::class, [
                'label' => 'Pecho (cm)',
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new GreaterThan(value: 0, message: 'El valor debe ser mayor que 0.'),
                    new LessThan(value: 1000, message: 'El valor no puede superar 999.99.'),
                ],
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej. 95.00',
                    'step' => '0.01',
                ],
            ])
            ->add('waistCm', NumberType::class, [
                'label' => 'Cintura (cm)',
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new GreaterThan(value: 0, message: 'El valor debe ser mayor que 0.'),
                    new LessThan(value: 1000, message: 'El valor no puede superar 999.99.'),
                ],
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej. 80.00',
                    'step' => '0.01',
                ],
            ])
            ->add('hipsCm', NumberType::class, [
                'label' => 'Caderas (cm)',
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new GreaterThan(value: 0, message: 'El valor debe ser mayor que 0.'),
                    new LessThan(value: 1000, message: 'El valor no puede superar 999.99.'),
                ],
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej. 100.00',
                    'step' => '0.01',
                ],
            ])
            ->add('armsCm', NumberType::class, [
                'label' => 'Brazos (cm)',
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new GreaterThan(value: 0, message: 'El valor debe ser mayor que 0.'),
                    new LessThan(value: 1000, message: 'El valor no puede superar 999.99.'),
                ],
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Ej. 35.00',
                    'step' => '0.01',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notas',
                'required' => false,
                'constraints' => [
                    new Length(max: 1000, maxMessage: 'Las notas no pueden superar 1000 caracteres.'),
                ],
                'attr' => [
                    'rows' => 3,
                    'class' => self::INPUT_CLASS,
                    'placeholder' => 'Observaciones opcionales...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BodyMeasurement::class,
        ]);
    }
}
