<?php

namespace App\Form;

use App\Entity\AthleteSession;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

class AthleteSessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('difficulty', IntegerType::class, [
                'label'       => 'Difficulté ressentie (0-10)',
                'required'    => false,
                'constraints' => [new Range(['min' => 0, 'max' => 10])],
                'attr'        => ['class' => 'form-input', 'min' => 0, 'max' => 10,
                                  'placeholder' => '5'],
            ])
            ->add('comment', TextareaType::class, [
                'label'    => 'Ressenti / Commentaire',
                'required' => false,
                'attr'     => ['class' => 'form-textarea', 'rows' => 4,
                              'placeholder' => "Comment s'est passée cette séance ? Sensations, douleurs, points positifs..."],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AthleteSession::class]);
    }
}
