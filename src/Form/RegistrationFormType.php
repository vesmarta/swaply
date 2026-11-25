<?php

namespace App\Form;

use App\Constant\Governorates;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First name',
                'attr' => ['placeholder' => 'Ahmed'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last name',
                'attr' => ['placeholder' => 'Ben Ali'],
            ])
            ->add('username', TextType::class, [
                'label' => 'Username',
                'attr' => ['placeholder' => 'ahmed_benali'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email address',
                'attr' => ['placeholder' => 'you@example.com', 'autocomplete' => 'email'],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone number',
                'attr' => ['placeholder' => '20123456'],
            ])
            ->add('governorate', ChoiceType::class, [
                'label' => 'Governorate',
                'choices' => Governorates::choices(),
                'placeholder' => 'Select your governorate',
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'label' => 'City / delegation',
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => ['autocomplete' => 'new-password'],
                    'help' => 'At least 8 characters.',
                ],
                'second_options' => [
                    'label' => 'Confirm password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'The two passwords must match.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Please choose a password.'),
                    new Assert\Length(
                        min: 8,
                        max: 4096,
                        minMessage: 'Your password must be at least {{ limit }} characters.',
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'I agree to the terms of service and privacy policy',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['Default', 'registration'],
        ]);
    }
}
