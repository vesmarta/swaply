<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'first_options' => [
                'label' => 'New password',
                'attr' => ['autocomplete' => 'new-password'],
                'help' => 'At least 8 characters.',
            ],
            'second_options' => [
                'label' => 'Confirm new password',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'invalid_message' => 'The two passwords must match.',
            'constraints' => [
                new Assert\NotBlank(message: 'Please choose a new password.'),
                new Assert\Length(
                    min: 8,
                    max: 4096,
                    minMessage: 'Your password must be at least {{ limit }} characters.',
                ),
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
