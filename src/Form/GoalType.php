<?php

namespace App\Form;

use App\Entity\Goal;
use App\Entity\Performance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GoalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $disciplines = ['' => null];
        foreach (Performance::DISCIPLINES as $label => $value) {
            $disciplines[$label] = $value;
        }

        $statuses = [];
        foreach (Goal::STATUSES as $label => $value) {
            $statuses[$label] = $value;
        }

        $builder
            ->add('title', TextType::class, [
                'label' => 'Objectif',
                'attr' => ['class' => 'form-input', 'placeholder' => 'ex: Passer sous les 11s au 100m'],
            ])
            ->add('discipline', ChoiceType::class, [
                'label' => 'Discipline',
                'choices' => $disciplines,
                'required' => false,
                'placeholder' => 'Générale',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('targetValue', NumberType::class, [
                'label' => 'Valeur cible',
                'required' => false,
                'scale' => 3,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('unit', ChoiceType::class, [
                'label' => 'Unité',
                'required' => false,
                'choices' => [
                    'Secondes' => 's',
                    'Mètres' => 'm',
                    'Points' => 'pts',
                ],
                'placeholder' => '—',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('deadline', DateType::class, [
                'label' => 'Échéance',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => $statuses,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Goal::class]);
    }
}
