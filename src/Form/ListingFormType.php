<?php

namespace App\Form;

use App\Constant\Governorates;
use App\Entity\Category;
use App\Entity\Listing;
use App\Enum\ListingCondition;
use App\Enum\ListingType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;

class ListingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'attr' => ['placeholder' => 'e.g. iPhone 12 64GB', 'maxlength' => 120],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Choose a category',
                'label' => 'Category',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 6, 'placeholder' => 'Describe the condition, any defects, and what you are looking for…'],
            ])
            ->add('condition', EnumType::class, [
                'class' => ListingCondition::class,
                'choice_label' => static fn (ListingCondition $c) => $c->label(),
                'placeholder' => 'Choose the condition',
                'label' => 'Condition',
            ])
            ->add('type', EnumType::class, [
                'class' => ListingType::class,
                'choice_label' => static fn (ListingType $t) => $t->label(),
                'label' => 'Listing type',
            ])
            ->add('priceTnd', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Price / estimated value (TND)',
                'help' => 'Required for sale listings. Use a dot for millimes, e.g. 89.500.',
                'data' => $options['price_tnd'],
                'attr' => ['placeholder' => 'e.g. 89.500', 'inputmode' => 'decimal'],
            ])
            ->add('governorate', ChoiceType::class, [
                'choices' => Governorates::choices(),
                'placeholder' => 'Choose a governorate',
                'label' => 'Governorate',
            ])
            ->add('city', TextType::class, [
                'required' => false,
                'label' => 'City / area',
                'attr' => ['placeholder' => 'e.g. El Menzah'],
            ])
            ->add('imageFiles', FileType::class, [
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'label' => 'Photos',
                'help' => 'Up to 5 photos, 5 MB each (JPG, PNG or WebP). The first photo is the cover.',
                'constraints' => [
                    new Count(max: 5, maxMessage: 'You can upload up to {{ limit }} photos.'),
                    new All([
                        new File(
                            maxSize: '5M',
                            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                            maxSizeMessage: 'Each photo must be 5 MB or smaller.',
                            mimeTypesMessage: 'Please upload JPG, PNG or WebP images only.',
                        ),
                    ]),
                ],
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Listing::class,
            'price_tnd' => null,
        ]);
        $resolver->setAllowedTypes('price_tnd', ['null', 'string']);
    }
}
