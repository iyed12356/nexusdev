<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 100)]
    private string $username;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    private string $password;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['ACTIVE', 'BANNED'])]
    private string $status = 'ACTIVE';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['VISITOR', 'REGISTERED', 'COACH', 'ORGANIZATION', 'ADMIN'])]
    private string $userType = 'REGISTERED';

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Coach::class)]
    private ?Coach $coach = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Order::class)]
    private Collection $orders;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: VirtualCurrency::class)]
    private Collection $virtualCurrencies;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Content::class)]
    private Collection $contents;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: ForumPost::class)]
    private Collection $forumPosts;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Comment::class)]
    private Collection $comments;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class)]
    private Collection $notifications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ProductPurchase::class)]
    private Collection $productPurchases;

    #[ORM\Column(type: 'boolean')]
    private bool $hasPlayer = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->orders = new ArrayCollection();
        $this->virtualCurrencies = new ArrayCollection();
        $this->contents = new ArrayCollection();
        $this->forumPosts = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->productPurchases = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): self
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        switch ($this->userType) {
            case 'ADMIN':
                $roles[] = 'ROLE_ADMIN';
                break;
            case 'COACH':
                $roles[] = 'ROLE_COACH';
                break;
            case 'ORGANIZATION':
                $roles[] = 'ROLE_ORGANIZATION';
                break;
            case 'VISITOR':
                $roles[] = 'ROLE_VISITOR';
                break;
            case 'REGISTERED':
            default:
                break;
        }

        return array_values(array_unique($roles));
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserType(): string
    {
        return $this->userType;
    }

    public function setUserType(string $userType): self
    {
        $this->userType = $userType;

        return $this;
    }

    public function getCoach(): ?Coach
    {
        return $this->coach;
    }

    public function setCoach(?Coach $coach): self
    {
        $this->coach = $coach;

        return $this;
    }

    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function getVirtualCurrencies(): Collection
    {
        return $this->virtualCurrencies;
    }

    public function getContents(): Collection
    {
        return $this->contents;
    }

    public function getForumPosts(): Collection
    {
        return $this->forumPosts;
    }

    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function getProductPurchases(): Collection
    {
        return $this->productPurchases;
    }

    public function hasPlayer(): bool
    {
        return $this->hasPlayer;
    }

    public function setHasPlayer(bool $hasPlayer): self
    {
        $this->hasPlayer = $hasPlayer;

        return $this;
    }
}
