<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\Booking;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('booking', EntityType::class, [
                'class' => Booking::class,
                'choice_label' => function ($booking) {
                    $service = $booking->getService();
                    $serviceTitle = $service ? $service->getTitle() : 'No Service';
                    $servicePrice = $service ? $service->getPrice() : '0.00';
                    return sprintf('Booking #%d - %s (₱%s)', $booking->getId(), $serviceTitle, $servicePrice);
                },
                'placeholder' => 'Select booking',
                'attr' => ['class' => 'border rounded px-2 py-1 w-full'],
                'choice_attr' => function ($booking) {
                    $service = $booking->getService();
                    $servicePrice = $service ? $service->getPrice() : '0.00';
                    return ['data-price' => $servicePrice];
                },
            ])
            ->add('amount', MoneyType::class, [
                'currency' => 'PHP',
                'attr' => ['class' => 'border rounded px-2 py-1 w-full']
            ])
            ->add('method', ChoiceType::class, [
                'choices' => [
                    'Cash' => 'Cash',
                    'Credit Card' => 'Credit Card',
                    'Gcash' => 'Gcash',
                ],
                'attr' => ['class' => 'border rounded px-2 py-1 w-full']
            ])
            ->add('paymentStatus', ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Confirmed' => 'Confirmed',
                    'Cancelled' => 'Cancelled',
                ],
                'attr' => ['class' => 'border rounded px-2 py-1 w-full']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
        ]);
    }
}
