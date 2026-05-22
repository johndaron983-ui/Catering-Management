<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{

    private UserPasswordHasherInterface $passwordHasher;
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);


        $admin = new User();
        $admin->setUsername('admin');
        $admin->setEmail('admin@gmail.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            'admin123'
        );
        $admin->setPassword($hashedPassword);
        $admin->setIsVerified(true);
        $admin->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($admin);


        $staff = new User();
        $staff->setUsername('staff');
        $staff->setEmail('staff@gmail.com');
        $staff->setRoles(['ROLE_STAFF']);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $staff,
            'staff123'
        );
        $staff->setPassword($hashedPassword);
        $staff->setIsVerified(true);
        $staff->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($staff);
        
        $john = new User();
        $john->setUsername('john');
        $john->setEmail('john@gmail.com');
        $john->setRoles(['ROLE_USER']);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $john,
            'john123'
        );
        $john->setPassword($hashedPassword);
        $john->setIsVerified(true);
        $john->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($john);

        $manager->flush();
    }
}
