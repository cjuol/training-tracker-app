<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<User> */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Correo electrónico',
                'attr' => [
                    'autocomplete' => 'email',
                    'placeholder' => 'tu@email.com',
                ],
                'constraints' => [
                    new NotBlank(message: 'El correo electrónico no puede estar vacío.'),
                    new Email(message: 'Por favor, introduce un correo electrónico válido.'),
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Nombre',
                'attr' => ['placeholder' => 'Juan'],
                'constraints' => [
                    new NotBlank(message: 'El nombre no puede estar vacío.'),
                    new Length(max: 100),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Apellido',
                'attr' => ['placeholder' => 'García'],
                'constraints' => [
                    new NotBlank(message: 'El apellido no puede estar vacío.'),
                    new Length(max: 100),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rol',
                'choices' => [
                    'Atleta' => 'ROLE_ATLETA',
                    'Entrenador' => 'ROLE_ENTRENADOR',
                ],
                'multiple' => true,
                'expanded' => true,
                'mapped' => true,
            ])
            ->add('plainPassword', PasswordType::class, [
                // mapped: false means this field is NOT automatically set on the entity.
                // We hash it manually in the controller before setting $user->setPassword().
                'mapped' => false,
                'label' => 'Contraseña',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'Mínimo 8 caracteres',
                ],
                'constraints' => [
                    new NotBlank(message: 'Por favor, introduce una contraseña.'),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'La contraseña debe tener al menos {{ limit }} caracteres.',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
