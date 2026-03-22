<?php

namespace App\Form;

use App\Entity\Athlete;
use App\Entity\Performance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AthleteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $disciplines = [];
        foreach (Performance::DISCIPLINES as $label => $value) {
            $disciplines[$label] = $value;
        }

        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'attr' => ['class' => 'form-input']])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'attr' => ['class' => 'form-input']])
            ->add('birthDate', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre',
                'required' => false,
                'choices' => ['Homme' => 'M', 'Femme' => 'F', 'Autre' => 'X'],
                'placeholder' => 'Non spécifié',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('discipline', ChoiceType::class, [
                'label' => 'Discipline principale',
                'choices' => $disciplines,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('ffaProfileUrl', TextType::class, [
                'label' => 'URL profil athle.fr',
                'required' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'https://bases.athle.fr/asp.net/athletes.aspx?...'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 4],
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Photo',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Format accepté : JPG, PNG, WEBP',
                    ]),
                ],
                'attr' => ['class' => 'form-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Athlete::class]);
    }
}
