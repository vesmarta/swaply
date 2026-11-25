<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin
            ->setEmail('admin@swaply.tn')
            ->setUsername('admin')
            ->setFirstName('Swaply')
            ->setLastName('Admin')
            ->setPhone('20000000')
            ->setCity('Tunis')
            ->setGovernorate('Tunis')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsVerified(true)
            ->setAgreeTerms(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin@12345'));

        $manager->persist($admin);

        $categories = [
            ['Electronics', 'electronics', 'bi-laptop'],
            ['Phones & Tablets', 'phones-tablets', 'bi-phone'],
            ['Fashion', 'fashion', 'bi-bag'],
            ['Home & Garden', 'home-garden', 'bi-house'],
            ['Sports & Outdoors', 'sports-outdoors', 'bi-bicycle'],
            ['Books & Media', 'books-media', 'bi-book'],
            ['Toys & Games', 'toys-games', 'bi-controller'],
            ['Baby & Kids', 'baby-kids', 'bi-emoji-smile'],
            ['Vehicles', 'vehicles', 'bi-car-front'],
            ['Tools & DIY', 'tools-diy', 'bi-tools'],
            ['Beauty & Health', 'beauty-health', 'bi-heart-pulse'],
            ['Collectibles', 'collectibles', 'bi-gem'],
            ['Other', 'other', 'bi-box'],
        ];

        foreach ($categories as [$name, $slug, $icon]) {
            $category = new Category();
            $category->setName($name)->setSlug($slug)->setIcon($icon);
            $manager->persist($category);
        }

        $manager->flush();
    }
}
