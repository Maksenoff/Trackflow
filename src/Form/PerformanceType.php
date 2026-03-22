<?php

namespace App\Form;

use App\Entity\Performance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PerformanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $disciplines = [];
        foreach (Performance::DISCIPLINES as $label => $value) {
            $disciplines[$label] = $value;
        }

        $builder
            ->add('discipline', ChoiceType::class, [
                'label' => 'Discipline',
                'choices' => $disciplines,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('value', NumberType::class, [
                'label' => 'Résultat',
                'scale' => 3,
                'attr' => ['class' => 'form-input', 'step' => '0.001', 'placeholder' => 'ex: 10.45 ou 65.20'],
            ])
            ->add('unit', ChoiceType::class, [
                'label' => 'Unité',
                'choices' => [
                    'Secondes (s)' => 's',
                    'Minutes:secondes' => 'min:s',
                    'Mètres (m)' => 'm',
                    'Points' => 'pts',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('recordedAt', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('isPersonalBest', CheckboxType::class, [
                'label' => 'Record personnel (PB)',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->add('isCompetition', CheckboxType::class, [
                'label' => 'En compétition',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Performance::class]);
    }
}
