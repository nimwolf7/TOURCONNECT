<?php

namespace App\Form;

use App\Entity\Booking;
use App\Entity\BudgetTracker;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BudgetTrackerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('booking', EntityType::class, [
                'class' => Booking::class,
                'choice_label' => function (Booking $booking): string {
                    $user = $booking->getUser();
                    $service = $booking->getService();
                    $customerLabel = $user?->getUsername() ?? 'No customer';
                    $serviceLabel = $service?->getTitle() ?? 'No service';

                    return sprintf(
                        '#%d - %s - %s',
                        $booking->getId() ?? 0,
                        $customerLabel,
                        $serviceLabel
                    );
                },
                'placeholder' => 'Select booking',
            ])
            ->add('category')
            ->add('amountPlanned')
            ->add('amountSpent')
            ->add('dateRange')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BudgetTracker::class,
        ]);
    }
}
