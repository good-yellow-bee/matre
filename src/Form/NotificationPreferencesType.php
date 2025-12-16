<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TestEnvironment;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for user notification preferences.
 */
class NotificationPreferencesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('notificationsEnabled', CheckboxType::class, [
                'label' => 'Enable Notifications',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
                'help' => 'Master toggle for all notifications',
            ])
            ->add('notificationTrigger', ChoiceType::class, [
                'label' => 'Notify When',
                'choices' => [
                    'All test runs' => 'all',
                    'Failures only' => 'failures',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'form-check',
                ],
                'help' => 'When to receive notifications',
            ])
            ->add('notifyByEmail', CheckboxType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
                'help' => 'Receive email notifications',
            ])
            ->add('notifyBySlack', CheckboxType::class, [
                'label' => 'Slack',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
                'help' => 'Trigger Slack notifications (shared channel)',
            ])
            ->add('notificationEnvironments', EntityType::class, [
                'class' => TestEnvironment::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Environments',
                'attr' => [
                    'class' => 'form-check',
                ],
                'help' => 'Select environments to receive notifications for',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
