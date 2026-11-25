<?php

namespace App\Form;

use App\Entity\TradeOffer;
use App\Enum\CashDirection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CounterOfferFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $labels = $options['cash_labels'];

        $builder
            ->add('cashDirection', EnumType::class, [
                'class' => CashDirection::class,
                'expanded' => true,
                'choice_label' => static fn (CashDirection $direction): string => $labels[$direction->value]
                    ?? $direction->label(),
                'choice_attr' => static fn (CashDirection $direction): array => [
                    'class' => 'btn-check',
                    'data-cash-direction' => $direction->value,
                ],
                'label' => 'Cash to balance the deal',
            ])
            ->add('cashAmountTnd', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Cash amount',
                'help' => 'For example 50.000.',
                'attr' => ['inputmode' => 'decimal'],
            ])
            ->add('message', TextareaType::class, [
                'required' => false,
                'label' => 'Message (optional)',
                'attr' => ['rows' => 3, 'maxlength' => 2000],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TradeOffer::class,
            'cash_labels' => [],
        ]);
        $resolver->setAllowedTypes('cash_labels', 'array');
    }
}
