<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numberOfPersons', IntegerType::class, [
                'label' => 'Number of Persons',
                'attr' => [
                    'class' => 'form-control app-input',
                    'min' => 1,
                    'max' => $options['max_capacity'],
                    'placeholder' => '1',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter the number of persons.'),
                    new Assert\Range(
                        min: 1,
                        max: $options['max_capacity'],
                        notInRangeMessage: 'Must be between 1 and {{ max }} persons.',
                    ),
                ],
            ])
            ->add('specialRequest', TextareaType::class, [
                'label' => 'Special Requests',
                'required' => false,
                'attr' => [
                    'class' => 'form-control app-input',
                    'rows' => 3,
                    'placeholder' => 'Dietary requirements, accessibility needs, room preferences...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
            'max_capacity' => 100,
        ]);

        $resolver->setAllowedTypes('max_capacity', 'int');
    }
}