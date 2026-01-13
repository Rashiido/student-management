<?php

namespace App\Entity;

use App\Repository\TeacherRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeacherRepository::class)]
class Teacher
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\ManyToOne(inversedBy: 'teachers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\OneToOne(inversedBy: 'teacher', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, StudentGroup>
     */
    #[ORM\OneToMany(targetEntity: StudentGroup::class, mappedBy: 'teacher')]
    private Collection $studentGroups;

    public function __construct()
    {
        $this->studentGroups = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): static
    {
        $this->school = $school;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, StudentGroup>
     */
    public function getStudentGroups(): Collection
    {
        return $this->studentGroups;
    }

    public function addStudentGroup(StudentGroup $studentGroup): static
    {
        if (!$this->studentGroups->contains($studentGroup)) {
            $this->studentGroups->add($studentGroup);
            $studentGroup->setTeacher($this);
        }

        return $this;
    }

    public function removeStudentGroup(StudentGroup $studentGroup): static
    {
        if ($this->studentGroups->removeElement($studentGroup)) {
            // set the owning side to null (unless already changed)
            if ($studentGroup->getTeacher() === $this) {
                $studentGroup->setTeacher(null);
            }
        }

        return $this;
    }
    public function __toString(): string
    {
        return $this->firstName . ' ' . $this->lastName . ' - ' . ($this->school ? $this->school->getName() : 'Non assigné');
    }
}
