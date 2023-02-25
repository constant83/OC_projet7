<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\ClientRepository;
use App\Repository\CustomerRepository;
use App\Service\DeleteService;
use App\Service\GetAllService;
use App\Service\GetClientCustomerService;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CustomerController extends AbstractController
{
    #[OA\Response(
        response: 200,
        description: 'Renvois la liste des customers (utilisateurs)',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['getCustomers']))
        )
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'La page que l\'on veux récupérer',
        schema: new OA\Schema(type:'int')
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Le nombre d\'éléments que l\'on veux récupérer',
        schema: new OA\Schema(type:'int')
    )]
    #[OA\Tag(name: 'Customer')]
    #[Route('/api/customers', name: 'api_customer', methods: ['GET'])]
    public function getCustomerList(CustomerRepository $customerRepository, Request $request, GetAllService $getAll): JsonResponse
    {
        $name = 'getCustomerList';
        $groups = ['getCustomers'];
        $tags = ['customerCache'];

        return $getAll->getAll($name, $groups, $customerRepository, $tags, $request);
    }

    #[OA\Response(
        response: 200,
        description: 'Renvois le détail d\'un customers (utilisateurs)',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['getCustomers']))
        )
    )]
    #[OA\Tag(name: 'Customer')]
    #[Route('/api/customers/{id}', name: 'api_detailCustomer', methods: ['GET'])]
    public function getDetailCustomer(Customer $customer, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getCustomers']);
        $jsonCustomer = $serializer->serialize($customer, 'json', $context);
        return new JsonResponse($jsonCustomer, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[OA\Response(
        response: 200,
        description: 'Renvois la liste des customers (utilisateurs) lié à un client',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['getClientCustomers']))
        )
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'La page que l\'on veux récupérer',
        schema: new OA\Schema(type:'int')
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Le nombre d\'éléments que l\'on veux récupérer',
        schema: new OA\Schema(type:'int')
    )]
    #[OA\Tag(name: 'ClientCustomer')]
    #[Entity('client', expr: 'repository.find(clientId)')]
    #[Route('api/clients/{clientId}/customers', name: 'api_clientCustomers', methods: ('GET'))]
    public function getClientCustomersList(CustomerRepository $customerRepository, Request $request, ClientRepository $clientRepository, GetClientCustomerService $getClientCustomersList)
    {
        $clientId = $request->get('clientId');
        $client = $clientRepository->find($request->get('clientId'));
        $customers = $customerRepository->findBy(['client' => $client]);
        if ($client && $customers) {

            $name = 'getClientCustomersList';
            $groups = ['getClientCustomers'];
            $tags = ['clientCustomersCache'];
    
            return $getClientCustomersList->getClientCustomerList($name, $groups, $tags, $clientId, $request);

        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[OA\Response(
        response: 200,
        description: 'Renvois le détail d\'un customer (utilisateurs) lié à un client',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['getClientCustomerDetail']))
        )
    )]
    #[OA\Tag(name: 'ClientCustomer')]
    #[Entity('client', options: ['id' => 'clientId'])]
    #[Entity('customer', options: ['id' => 'customerId'])]
    #[Route('api/clients/{clientId}/customers/{customerId}', name: 'api_clientCustomerDetail', methods: ('GET'))]
    public function getClientCustomersDetail(Customer $customer, SerializerInterface $serializer)
    {
        $context = SerializationContext::create()->setGroups(['getClientCustomerDetail']);
        $jsonClientCustomers = $serializer->serialize($customer, 'json', $context);
        return new JsonResponse($jsonClientCustomers, Response::HTTP_OK, [], true);
    }

    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisant pour supprimer un utilisateur')]
    #[OA\Response(
        response: 204,
        description: 'Supprime le customer (utilisateur) lié à un client',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: []))
        )
    )]
    #[OA\Tag(name: 'ClientCustomer')]
    #[Entity('customer', options: ['id' => 'customerId'])]
    #[Route('api/clients/{clientId}/customers/{customerId}', name: 'api_deleteClientCustomer', methods:['DELETE'])]
    public function deleteClientCustomer(Customer $customer, DeleteService $delete): JsonResponse
    {
        $cacheName = ["clientCustomersCache"];
        $delete->delete($cacheName, $customer);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisant pour créer un utilisateur')]
    #[OA\Response(
        response: 201,
        description: 'Créer un customer (utilisateur) lié à un client',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: []))
        )
    )]
    #[OA\Tag(name: 'ClientCustomer')]
    #[Route('api/clients/{clientId}/customers', name: 'api_createClientCustomer', methods:['POST'])]
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

        $context = SerializationContext::create()->setGroups(['customer', 'client']);

        $jsonCustomer = $serializer->serialize($customer, 'json', $context);

        return new JsonResponse($jsonCustomer, Response::HTTP_CREATED, [], true);
    }

    #[OA\Response(
        response: 204,
        description: 'Modifier un customer',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: []))
            )
        )]
    #[OA\Tag(name: 'Customer')]
    #[Entity('customer', options: ['id' => 'customerId'])]
    #[Entity('client', options: ['id' => 'clientId'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisant pour modifier un customer')]
    #[Route('/api/clients/{clientId}/customers/{customerId}', name:'api_updateCustomer', methods:['PUT'])]
    public function updateCustomer(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ClientRepository $clientRepository): JsonResponse
    {
        $updatedCustomer = $serializer->deserialize($request->getContent(),
            Customer::class,
            'json',
        );
        $client = $clientRepository->find($request->get('clientId'));
        $updatedCustomer->setClient($client);

        $em->persist($updatedCustomer);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
