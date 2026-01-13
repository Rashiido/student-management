<?php

namespace App\Entity;

use App\Repository\SchoolRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolRepository::class)]
class School
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    /**
     * @var Collection<int, Teacher>
     */
    #[ORM\OneToMany(targetEntity: Teacher::class, mappedBy: 'school')]
    private Collection $teachers;

    /**
     * @var Collection<int, StudentGroup>
     */
    #[ORM\OneToMany(targetEntity: StudentGroup::class, mappedBy: 'school')]
    private Collection $studentGroups;

    /**
     * @var Collection<int, Student>
     */
    #[ORM\OneToMany(targetEntity: Student::class, mappedBy: 'school')]
    private Collection $students;

    public function __construct()
    {
        $this->teachers = new ArrayCollection();
        $this->studentGroups = new ArrayCollection();
        $this->students = new ArrayCollection();
    }

    // ========== BASIC GETTERS AND SETTERS ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

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

    // ========== TEACHERS RELATIONSHIP ==========

    /**
     * @return Collection<int, Teacher>
     */
    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    public function addTeacher(Teacher $teacher): static
    {
        if (!$this->teachers->contains($teacher)) {
            $this->teachers->add($teacher);
            $teacher->setSchool($this);
        }

        return $this;
    }

    public function removeTeacher(Teacher $teacher): static
    {
        if ($this->teachers->removeElement($teacher)) {
            // set the owning side to null (unless already changed)
            if ($teacher->getSchool() === $this) {
                $teacher->setSchool(null);
            }
        }

        return $this;
    }

    // ========== STUDENT GROUPS RELATIONSHIP ==========

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
            $studentGroup->setSchool($this);
        }

        return $this;
    }

    public function removeStudentGroup(StudentGroup $studentGroup): static
    {
        if ($this->studentGroups->removeElement($studentGroup)) {
            // set the owning side to null (unless already changed)
            if ($studentGroup->getSchool() === $this) {
                $studentGroup->setSchool(null);
            }
        }

        return $this;
    }

    // ========== STUDENTS RELATIONSHIP ==========

    /**
     * @return Collection<int, Student>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function addStudent(Student $student): static
    {
        if (!$this->students->contains($student)) {
            $this->students->add($student);
            $student->setSchool($this);
        }

        return $this;
    }

    public function removeStudent(Student $student): static
    {
        if ($this->students->removeElement($student)) {
            // set the owning side to null (unless already changed)
            if ($student->getSchool() === $this) {
                $student->setSchool(null);
            }
        }

        return $this;
    }

    // Optional: Add a __toString method for better display
    public function __toString(): string
    {
        return $this->name ?? 'School';
    }
}
