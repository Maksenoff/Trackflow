<?php

namespace App\Form;

use App\Entity\CompetitionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompetitionTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du type',
                'attr'  => ['class' => 'form-input', 'placeholder' => 'ex: Route, Piste, Cross...'],
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur',
                'attr'  => ['class' => 'w-12 h-10 rounded-lg cursor-pointer bg-transparent border border-gray-700 p-0.5'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CompetitionType::class]);
    }
}
