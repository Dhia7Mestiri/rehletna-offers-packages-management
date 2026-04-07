<?php

namespace App\Form;

use App\Entity\Offer;
use App\Entity\Service;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'attr' => [
                    'class' => 'form-control app-input',
                    'placeholder' => 'Enter offer title'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control app-input',
                    'rows' => 5,
                    'placeholder' => 'Describe the offer'
                ]
            ])
            ->add('promoPrice', MoneyType::class, [
                'label' => 'Promotional Price',
                'currency' => 'TND',
                'attr' => [
                    'class' => 'form-control app-input'
                ]
            ])
            ->add('originalPrice', MoneyType::class, [
                'label' => 'Original Price',
                'currency' => 'TND',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'class' => 'form-control app-input'
                ]
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Start Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => 'form-control app-input'
                ]
            ])
            ->add('endDate', DateType::class, [
                'label' => 'End Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => 'form-control app-input'
                ]
            ])
            ->add('imageUrl', TextType::class, [
                'label' => 'Image URL',
                'required' => false,
                'attr' => [
                    'class' => 'form-control app-input',
                    'placeholder' => 'https://...'
                ]
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'Capacity',
                'required' => false,
                'attr' => [
                    'class' => 'form-control app-input',
                    'placeholder' => 'Number of places'
                ]
            ])
            ->add('location', TextType::class, [
                'label' => 'Location',
                'required' => false,
                'attr' => [
                    'class' => 'form-control app-input',
                    'placeholder' => 'Destination / city'
                ]
            ])
            ->add('services', EntityType::class, [
                'class' => Service::class,
                'choice_label' => function (Service $service) {
                    return $service->getName() . ' (' . $service->getType() . ')';
                },
                'mapped' => false,
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Included Services',
                'attr' => [
                    'class' => 'form-select app-input',
                    'size' => 8
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offer::class,
        ]);
    }
}