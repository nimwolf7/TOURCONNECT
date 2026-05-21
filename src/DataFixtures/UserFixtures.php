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
        $repo = $manager->getRepository(User::class);

        $adminUser = $repo->findOneBy(['username' => 'admin']) ?? new User();
        $adminUser->setUsername('admin');
        $adminUser->setEmail('admin@example.com');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $adminUser->setIsVerified(true);
        $adminUser->setVerifiedAt(new \DateTimeImmutable());
        $hashedPassword = $this->passwordHasher->hashPassword($adminUser, 'admin123');
        $adminUser->setPassword($hashedPassword);
        $manager->persist($adminUser);
        $this->addReference('user_admin', $adminUser);

        $staff = $repo->findOneBy(['username' => 'staff']) ?? new User();
        $staff->setUsername('staff');
        $staff->setEmail('staff@example.com');
        $staff->setRoles(['ROLE_STAFF']);
        $staff->setIsVerified(true);
        $staff->setVerifiedAt(new \DateTimeImmutable());
        $hashedPassword = $this->passwordHasher->hashPassword($staff, 'staff123');
        $staff->setPassword($hashedPassword);
        $manager->persist($staff);
        $this->addReference('user_staff', $staff);
    
        $manager->flush();
    }
}
