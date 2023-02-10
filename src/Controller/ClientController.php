<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Customer;
use App\Repository\ClientRepository;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;


class ClientController extends AbstractController
{
    #[Route('/api/clients', name: 'api_client', methods: ['GET'])]
    #[Cache(expires: 'tomorrow', public: true)]
    public function getClientList(ClientRepository $clientRepository, SerializerInterface $serializer): JsonResponse
    {
        $clientList = $clientRepository->findAll();
        $jsonClientList = $serializer->serialize($clientList, 'json', ['groups' => 'getClients']);

        return new JsonResponse($jsonClientList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/clients/{id}', name: 'api_detailClient', methods: ['GET'])]
    #[Cache(expires: 'tomorrow', public: true)]
    public function getDetailClient(Client $client, SerializerInterface $serializer): JsonResponse 
    {
        $jsonClient = $serializer->serialize($client, 'json', [ 'groups' => 'getClients']);
        return new JsonResponse($jsonClient, Response::HTTP_OK, ['accept' => 'json'], true);
    }

   #[Route('/api/clients/{id}', name: 'api_deleteClient', methods:['DELETE'])]
   #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisant pour supprimer un client')]
   public function deleteClient(Client $client, ManagerRegistry $doctrine): JsonResponse
   {
        $em = $doctrine->getManager();
        $em->remove($client);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
   }

   #[Route('/api/clients', name:'api_createClient', methods: ['POST'])]
   #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisant pour créer un client')]
   public function createUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
   {
        $client = $serializer->deserialize($request->getContent(), Client::class, 'json');

        // Vérif. des erreurs
        $errors = $validator->validate($client);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($client);
        $em->flush();

        $jsonClient = $serializer->serialize($client, 'json');

        return new JsonResponse($jsonClient, Response::HTTP_CREATED);
    }

    #[Route('api/clients/{clientId}/customers', name: 'api_clientCustomers', methods: ('GET'))]
    public function getClientCustomersList(SerializerInterface $serializer, CustomerRepository $customerRepository, Request $request, TagAwareCacheInterface $cache)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getClientCustomersList-" . $page . "-" . $limit;

        $jsonClientCustomers = $cache->get(
            $idCache,
            function (ItemInterface $item) use ($customerRepository, $page, $limit, $serializer) {
                $item->tag("clientCustomersCache");
                $clientCustomers = $customerRepository->findAllWithPagination($page, $limit);
                return $serializer->serialize($clientCustomers, 'json', ['groups' => 'getClientCustomers']);
            });
        
            return new JsonResponse($jsonClientCustomers, Response::HTTP_OK, [], true);
    }

    #[Route('api/clients/{clientId}/customers/{customerId}', name: 'api_clientCustomerDetail', methods: ('GET'))]
    #[Entity('customer', options: ['id' => 'customerId'])]
    public function getClientCustomersDetail(Customer $customer, SerializerInterface $serializer)
    {

        $jsonClientCustomers = $serializer->serialize($customer, 'json', ['groups' => 'getClientCustomerDetail']);
        return new JsonResponse($jsonClientCustomers, Response::HTTP_OK, [], true);
    }

    #[Route('api/clients/{clientId}/customers/{customerId}', name: 'api_delteClientCustomer', methods:['DELETE'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisant pour supprimer un utilisateur')]
    #[Entity('customer', options: ['id' => 'customerId'])]
    public function deleteClientCustomer(ManagerRegistry $doctrine, Customer $customer, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["clientCustomersCache"]);
        $em = $doctrine->getManager();
        $em->remove($customer);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('api/clients/{clientId}/customers', name: 'api_createClientCustomer', methods:['POST'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisant pour créer un utilisateur')]
    public function createClientCustomer(int $clientId, SerializerInterface $serializer, Request $request, ValidatorInterface $validator, ClientRepository $clientRepository, EntityManagerInterface $em)
    {
        $customer = $serializer->deserialize($request->getContent(), Customer::class, 'json');

        $errors = $validator->validate($customer);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $customer->setClient($clientRepository->find($clientId));
        $customer->setRoles(['ROLE_USER']);

        $em->persist($customer);
        $em->flush();

        $jsonCustomer = $serializer->serialize($customer, 'json', ['groups' => ['customer', 'client']], true);

        return new JsonResponse($jsonCustomer, Response::HTTP_CREATED, [], true);
    }
}
