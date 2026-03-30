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
                'label' => 'Service to Book',
                'placeholder' => 'Select a service',
                'required' => true,
            ])
            ->add('quantity', null, [
                'attr' => ['min' => 1, 'step' => 1],
            ])
            ->add('status', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Confirmed' => 'Confirmed',
                    'Cancelled' => 'Cancelled',
                ],
                'placeholder' => 'Select status',
                'required' => true,
            ])
            ->add('bookingDate')
            ->add('totalAmount', null, [
                'attr' => ['readonly' => true],
            ])
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
