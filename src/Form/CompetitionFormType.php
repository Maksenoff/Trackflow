<?php

namespace App\Form;

use App\Entity\Competition;
use App\Entity\CompetitionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CompetitionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Nom de la compétition',
                'attr'  => ['class' => 'form-input', 'placeholder' => 'ex: Championnat de France 10 000m'],
            ])
            ->add('date', DateType::class, [
                'label'  => 'Date',
                'widget' => 'single_text',
                'attr'   => ['class' => 'form-input'],
            ])
            ->add('location', TextType::class, [
                'label'    => 'Lieu',
                'required' => false,
                'attr'     => ['class' => 'form-input', 'placeholder' => 'ex: Paris, Stade Charlety'],
            ])
            ->add('competitionType', EntityType::class, [
                'label'        => 'Type de compétition',
                'class'        => CompetitionType::class,
                'choice_label' => 'name',
                'placeholder'  => 'Choisir un type...',
                'required'     => false,
                'attr'         => ['class' => 'form-select'],
            ])
            ->add('websiteUrl', UrlType::class, [
                'label'         => 'Site officiel',
                'required'      => false,
                'default_protocol' => 'https',
                'attr'          => ['class' => 'form-input', 'placeholder' => 'https://...'],
            ])
            ->add('documentFile', FileType::class, [
                'label'    => 'Circulaire (PDF)',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['accept' => '.pdf,.doc,.docx'],
                'constraints' => [
                    new File([
                        'maxSize'          => '10M',
                        'mimeTypes'        => ['application/pdf', 'application/msword',
                                              'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                        'mimeTypesMessage' => 'Formats acceptés : PDF, DOC, DOCX.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Notes',
                'required' => false,
                'attr'     => ['class' => 'form-textarea', 'rows' => 5,
                              'placeholder' => 'Informations complémentaires, consignes, programme...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Competition::class]);
    }
}
