<?php

namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Service Title',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a service title',
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'Title must be at least {{ limit }} characters',
                        'maxMessage' => 'Title cannot be longer than {{ limit }} characters',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a description',
                    ]),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'Description must be at least {{ limit }} characters',
                    ]),
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Price',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a price',
                    ]),
                    new Positive([
                        'message' => 'Price must be a positive number',
                    ]),
                ],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Category',
                'choices' => [
                    'Beach & Island' => 'Beach & Island',
                    'Mountain & Nature' => 'Mountain & Nature',
                    'Cultural & Historical' => 'Cultural & Historical',
                    'Adventure & Extreme' => 'Adventure & Extreme',
                    'Wellness & Relaxation' => 'Wellness & Relaxation',
                    'Food & Culinary' => 'Food & Culinary',
                ],
                'placeholder' => 'Select a category',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select a category',
                    ]),
                ],
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock Quantity',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter stock quantity',
                    ]),
                    new GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'Stock cannot be negative',
                    ]),
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Service Image (JPG, PNG)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/jpg',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPG, PNG)',
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }
}