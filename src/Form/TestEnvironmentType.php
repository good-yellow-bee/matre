<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TestEnvironment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TestEnvironmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Environment Name',
                'help' => 'Unique name like "dev-us" or "stage-es"',
                'attr' => ['class' => 'form-control', 'placeholder' => 'dev-us'],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code',
                'help' => 'Short code like "dev", "stage", "preprod"',
                'attr' => ['class' => 'form-control', 'placeholder' => 'dev'],
            ])
            ->add('region', TextType::class, [
                'label' => 'Region',
                'help' => 'Region code like "us", "es", "uk"',
                'attr' => ['class' => 'form-control', 'placeholder' => 'us'],
            ])
            ->add('baseUrl', UrlType::class, [
                'label' => 'Base URL',
                'help' => 'Full URL to the Magento storefront',
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://dev-us.example.com/'],
            ])
            ->add('backendName', TextType::class, [
                'label' => 'Backend Name',
                'help' => 'Admin panel path (typically "admin")',
                'attr' => ['class' => 'form-control', 'placeholder' => 'admin'],
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
            'data_class' => TestEnvironment::class,
        ]);
    }
}
