<?php

namespace App\Form;

use App\Constant\Governorates;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First name',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last name',
            ])
            ->add('username', TextType::class, [
                'label' => 'Username',
                'help' => 'Letters, numbers and underscores only.',
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone number',
                'attr' => ['placeholder' => '20123456'],
                'help' => 'Tunisian mobile, e.g. 20123456.',
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
