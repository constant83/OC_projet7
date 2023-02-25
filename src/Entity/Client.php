<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Hateoas\Relation("self", href = @Hateoas\Route( "api_detailClient", parameters= {"id" = "expr(object.getId())"},),
 * exclusion = @Hateoas\Exclusion(groups="getClients"))
 *
 * @Hateoas\Relation("list", href = @Hateoas\Route( "api_client"),
 * exclusion = @Hateoas\Exclusion(groups="getClients"))
 *
 * @Hateoas\Relation("delete", href = @Hateoas\Route( "api_deleteClient", parameters= {"id" = "expr(object.getId())"},),
 * exclusion = @Hateoas\Exclusion(groups="getClients"))
 *
 * @Hateoas\Relation("post", href = @Hateoas\Route( "api_createClient"),
 * exclusion = @Hateoas\Exclusion(groups="getClients"))
 */
#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getClients"])]
    private int $id;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['getClients','getClientCustomers'])]
    private string $name;

    #[ORM\Column]
    #[Assert\NotBlank]
    private string $password;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Customer::class, orphanRemoval: true)]
    #[Groups(['client'])]
    private Collection $customers;

    public function __construct()
    {
        $this->customers = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

        /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->name;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_CLIENT';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword;
    }

    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    /**
     * @return Collection<int, Customer>
     */
    public function getCustomers(): Collection
    {
        return $this->customers;
    }

    public function addCustomer(Customer $customer): self
    {
        if (!$this->customers->contains($customer)) {
            $this->customers->add($customer);
            $customer->setClient($this);
        }

        return $this;
    }

    public function removeCustomer(Customer $customer): self
    {
        if ($this->customers->removeElement($customer)) {
            // set the owning side to null (unless already changed)
            if ($customer->getClient() === $this) {
                $customer->setClient(null);
            }
        }

        return $this;
    }
}
