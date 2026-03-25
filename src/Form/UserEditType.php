<?php

namespace App\Form;

use App\Entity\Athlete;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [new NotBlank(), new Length(['max' => 100])],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank(), new Length(['max' => 100])],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add('roles', ChoiceType::class, [
                'label'    => 'Rôle',
                'choices'  => [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Coach'          => 'ROLE_COACH',
                    'Athlète'        => 'ROLE_ATHLETE',
                ],
                'multiple' => false,
                'expanded' => false,
                'mapped'   => false,
            ])
            ->add('linkedAthlete', EntityType::class, [
                'label'        => 'Profil athlète lié',
                'class'        => Athlete::class,
                'choice_label' => fn(Athlete $a) => $a->getFullName(),
                'required'     => false,
                'placeholder'  => '— Aucun —',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
