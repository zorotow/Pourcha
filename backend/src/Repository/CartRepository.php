<?php

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function findActiveCartByUser(User $user): ?Cart
    {
        return $this->findOneBy([
            'user' => $user,
            'status' => 'active'
        ]);
    }

    public function getOrCreateCart(User $user): Cart
    {
        $cart = $this->findActiveCartByUser($user);

        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->getEntityManager()->persist($cart);
            $this->getEntityManager()->flush();
        }

        return $cart;
    }
}
