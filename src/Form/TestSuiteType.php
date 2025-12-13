<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TestSuite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TestSuiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Suite Name',
                'help' => 'Unique name for this test suite',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Checkout Flow Tests'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'MFTF Group' => TestSuite::TYPE_MFTF_GROUP,
                    'MFTF Test' => TestSuite::TYPE_MFTF_TEST,
                    'Playwright Group' => TestSuite::TYPE_PLAYWRIGHT_GROUP,
                    'Playwright Test' => TestSuite::TYPE_PLAYWRIGHT_TEST,
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('testPattern', TextType::class, [
                'label' => 'Test Pattern',
                'help' => 'Test name, group name, or grep pattern',
                'attr' => ['class' => 'form-control', 'placeholder' => 'CheckoutTest or @checkout'],
            ])
            ->add('cronExpression', TextType::class, [
                'label' => 'Cron Expression',
                'required' => false,
                'help' => 'Schedule (e.g., "0 2 * * *" for 2 AM daily). Leave empty for manual runs only.',
                'attr' => ['class' => 'form-control', 'placeholder' => '0 2 * * *'],
            ])
            ->add('estimatedDuration', IntegerType::class, [
                'label' => 'Estimated Duration (minutes)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'min' => 1],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TestSuite::class,
        ]);
    }
}
