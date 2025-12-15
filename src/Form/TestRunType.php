<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TestEnvironment;
use App\Entity\TestSuite;
use App\Repository\TestEnvironmentRepository;
use App\Repository\TestSuiteRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TestRunType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('environment', EntityType::class, [
                'class' => TestEnvironment::class,
                'choice_label' => 'name',
                'query_builder' => fn (TestEnvironmentRepository $repo) => $repo->createQueryBuilder('e')
                    ->where('e.isActive = :active')
                    ->setParameter('active', true)
                    ->orderBy('e.name', 'ASC'),
                'label' => 'Environment',
                'placeholder' => 'Select environment',
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()],
            ])
            ->add('suite', EntityType::class, [
                'class' => TestSuite::class,
                'choice_label' => fn (TestSuite $suite) => $suite->getTypeLabel() . ': ' . $suite->getName(),
                'query_builder' => fn (TestSuiteRepository $repo) => $repo->createQueryBuilder('s')
                    ->where('s.isActive = :active')
                    ->setParameter('active', true)
                    ->orderBy('s.name', 'ASC'),
                'label' => 'Test Suite',
                'placeholder' => 'Select test suite',
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
