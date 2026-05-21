<?php

namespace App\Form;

use App\Entity\Booking;
use App\Entity\User;
use App\Entity\Service;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('service', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'title',
                'choice_attr' => static function (?Service $service): array {
                    return [
                        'data-price' => $service?->getPrice() ?? '0',
                    ];
                },
                'label' => 'Service to Book',
                'placeholder' => 'Select a service',
                'required' => true,
            ])
            ->add('quantity')
            ->add('status', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Complete' => 'Complete',
                    'Refund' => 'Refund',
                    'Cancelled' => 'Cancelled',
                ],
                'placeholder' => 'Select status',
                'required' => true,
            ])
            ->add('bookingDate')
            ->add('totalAmount')
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
        ]);
    }
}
