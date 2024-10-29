<?php
// src/Form/ProductType.php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
            ])
            ->add('kategory', TextType::class, [
                'required' => false,
            ])
            ->add('description', TextType::class, [
                'required' => false,
            ])
            ->add('number_in_stock', IntegerType::class, [
                'required' => true,
            ])
            ->add('image_urls', FileType::class, [
                'label' => 'Upload Images',
                'multiple' => true,  // 支持多文件上传
                'mapped' => false,   // 不直接映射到实体
                'required' => false,
                'data_class' => null, // 允许传递文件数组
            ])
            ->add('width', IntegerType::class, [
                'required' => false,
            ])
            ->add('height', IntegerType::class, [
                'required' => false,
            ])
            ->add('length', IntegerType::class, [
                'required' => false,
            ])
            ->add('weight', IntegerType::class, [
                'required' => false,
            ])
            ->add('material', TextType::class, [
                'required' => false,
            ])
            ->add('color', TextType::class, [
                'required' => false,
            ])
            ->add('price', IntegerType::class, [
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}