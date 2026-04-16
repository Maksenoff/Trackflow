<?php

namespace App\Form;

use App\Entity\Athlete;
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
                'attr' => ['class' => 'form-input'],
            ])
            ->add('licenseNumber', TextType::class, [
                'label' => 'Numéro de licence',
                'required' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex : 123456'],
            ])
            ->add('ffaProfileUrl', TextType::class, [
                'label' => 'URL profil athle.fr',
                'required' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'https://www.athle.fr/athletes/XXXXX/resultats'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['class' => 'form-input', 'rows' => 3, 'placeholder' => 'Informations complémentaires…'],
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
 