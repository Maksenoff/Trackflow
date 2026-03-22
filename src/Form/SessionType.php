<?php

namespace App\Form;

use App\Entity\Session;
use App\Entity\TrainingType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la séance',
                'attr'  => ['class' => 'form-input', 'placeholder' => 'ex: Fractionné 10×200m'],
            ])
            ->add('date', DateType::class, [
                'label'  => 'Date',
                'widget' => 'single_text',
                'attr'   => ['class' => 'form-input'],
            ])
            ->add('trainingType', EntityType::class, [
                'label'        => 'Type de séance',
                'class'        => TrainingType::class,
                'choice_label' => 'name',
                'placeholder'  => 'Choisir un type...',
                'required'     => false,
                'attr'         => ['class' => 'form-select'],
            ])
            ->add('durationMinutes', IntegerType::class, [
                'label'    => 'Durée (minutes)',
                'required' => false,
                'attr'     => ['class' => 'form-input', 'min' => 1, 'max' => 480],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Programme de la séance',
                'required' => false,
                'attr'     => ['class' => 'form-textarea', 'rows' => 10,
                              'placeholder' => "Écrivez le détail de la séance :\n\nÉchauffement : 15 min footing + gammes\n\nCorps de séance :\n- 3×(4×200m) allure PMA, R=45s/3min\n\nRetour au calme : 10 min"],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Session::class]);
    }
}
