<?php

namespace App\Entity;

use App\Repository\StudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
class Student
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateOfBirth = null;

    #[ORM\Column(length: 50)]
    private ?string $niveauScolaire = null;

    #[ORM\ManyToOne(inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StudentGroup $studentGroup = null;

    #[ORM\ManyToOne(inversedBy: 'students')] // <-- ADD THIS
    #[ORM\JoinColumn(nullable: false)] // <-- ADD THIS
    private ?School $school = null; // <-- ADD THIS PROPERTY

    /**
     * @var Collection<int, Attendance>
     */
    #[ORM\OneToMany(targetEntity: Attendance::class, mappedBy: 'student')]
    private Collection $attendances;

    public function __construct()
    {
        $this->attendances = new ArrayCollection();
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

    public function getDateOfBirth(): ?\DateTime
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTime $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }

    public function getNiveauScolaire(): ?string
    {
        return $this->niveauScolaire;
    }

    public function setNiveauScolaire(string $niveauScolaire): static
    {
        $this->niveauScolaire = $niveauScolaire;

        return $this;
    }

    public function getStudentGroup(): ?StudentGroup
    {
        return $this->studentGroup;
    }

    public function setStudentGroup(?StudentGroup $studentGroup): static
    {
        $this->studentGroup = $studentGroup;

        return $this;
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function getAttendances(): Collection
    {
        return $this->attendances;
    }

    public function addAttendance(Attendance $attendance): static
    {
        if (!$this->attendances->contains($attendance)) {
            $this->attendances->add($attendance);
            $attendance->setStudent($this);
        }

        return $this;
    }

    public function removeAttendance(Attendance $attendance): static
    {
        if ($this->attendances->removeElement($attendance)) {
            // set the owning side to null (unless already changed)
            if ($attendance->getStudent() === $this) {
                $attendance->setStudent(null);
            }
        }

        return $this;
    }

    // ADD THESE TWO METHODS:
    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): static
    {
        $this->school = $school;

        return $this;
    }
}
