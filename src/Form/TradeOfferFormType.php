<?php

namespace App\Form;

use App\Entity\Listing;
use App\Entity\TradeOffer;
use App\Enum\CashDirection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TradeOfferFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('offeredListing', EntityType::class, [
                'class' => Listing::class,
                'choices' => $options['my_listings'],
                'choice_label' => static fn (Listing $l): string => $l->getTitle(),
                'expanded' => true,
                'label' => 'Your item to offer',
                'choice_attr' => static fn (Listing $l): array => [
                    'class' => 'btn-check',
                    'data-item-title' => $l->getTitle(),
                    'data-item-price' => (string) ($l->getPriceMillimes() ?? ''),
                    'data-item-image' => null !== $l->getCoverImage() ? '/uploads/listings/'.$l->getCoverImage()->getFilename() : '',
                    'data-item-cond' => $l->getCondition()?->label() ?? '',
                ],
            ])
            ->add('cashDirection', EnumType::class, [
                'class' => CashDirection::class,
                'expanded' => true,
                'choice_label' => static fn (CashDirection $direction): string => self::labelFor($direction),
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
                'help' => 'For example 50.000. Leave empty when no cash is involved.',
                'attr' => ['placeholder' => 'e.g. 50.000', 'inputmode' => 'decimal'],
            ])
            ->add('message', TextareaType::class, [
                'required' => false,
                'label' => 'Message (optional)',
                'attr' => ['rows' => 3, 'maxlength' => 2000, 'placeholder' => 'Explain your offer, pickup preferences…'],
            ]);
    }

    public static function labelFor(CashDirection $direction): string
    {
        return match ($direction) {
            CashDirection::NONE => 'Straight swap — no cash',
            CashDirection::REQUESTER_PAYS => 'I add cash on top',
            CashDirection::OWNER_PAYS => 'I ask for cash back',
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TradeOffer::class,
            'my_listings' => [],
        ]);
        $resolver->setAllowedTypes('my_listings', 'array');
    }
}
