<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TestEnvironment;
use App\Entity\TestRun;
use App\Entity\TestSuite;
use App\Repository\TestEnvironmentRepository;
use App\Repository\TestSuiteRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
            ->add('type', ChoiceType::class, [
                'label' => 'Test Type',
                'choices' => [
                    'MFTF' => TestRun::TYPE_MFTF,
                    'Playwright' => TestRun::TYPE_PLAYWRIGHT,
                    'Both' => TestRun::TYPE_BOTH,
                ],
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()],
            ])
            ->add('suite', EntityType::class, [
                'class' => TestSuite::class,
                'choice_label' => 'name',
                'query_builder' => fn (TestSuiteRepository $repo) => $repo->createQueryBuilder('s')
                    ->where('s.isActive = :active')
                    ->setParameter('active', true)
                    ->orderBy('s.name', 'ASC'),
                'label' => 'Test Suite',
                'required' => false,
                'placeholder' => 'Select suite (optional)',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('testFilter', TextType::class, [
                'label' => 'Test Filter',
                'required' => false,
                'help' => 'Test name, group name, or grep pattern. Overrides suite pattern if provided.',
                'attr' => ['class' => 'form-control', 'placeholder' => 'MOEC1625 or @checkout'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
