<?php

namespace App\DataFixtures;

use App\Entity\School;
use App\Entity\StudentGroup;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\User;
use App\Entity\Schedule;
use App\Entity\Attendance;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        echo "🚀 Loading fixtures...\n";

        // ========== SCHOOLS ==========
        echo "Creating schools...\n";

        $school1 = new School();
        $school1->setName('Lycée Descartes');
        $school1->setAddress('123 Avenue de Paris, 75000 Paris');
        $school1->setPhone('01 23 45 67 89');
        $manager->persist($school1);

        $school2 = new School();
        $school2->setName('Collège Victor Hugo');
        $school2->setAddress('456 Boulevard des Écoles, 69000 Lyon');
        $school2->setPhone('04 56 78 90 12');
        $manager->persist($school2);

        $manager->flush();
        echo "✅ Schools created\n";

        // ========== USERS (Admin & Teachers) ==========
        echo "Creating users and teachers...\n";

        // ADMIN User
        $adminUser = new User();
        $adminUser->setUsername('admin');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword($adminUser, 'admin123');
        $adminUser->setPassword($hashedPassword);
        $manager->persist($adminUser);

        // TEACHERS for School 1
        $teacher1 = $this->createTeacher('marie.durand', 'Marie', 'Durand', 'teacher123', $school1, $manager);
        $teacher2 = $this->createTeacher('jean.martin', 'Jean', 'Martin', 'teacher123', $school1, $manager);

        // TEACHERS for School 2
        $teacher3 = $this->createTeacher('sophie.leroy', 'Sophie', 'Leroy', 'teacher123', $school2, $manager);
        $teacher4 = $this->createTeacher('pierre.duval', 'Pierre', 'Duval', 'teacher123', $school2, $manager);

        $manager->flush();
        echo "✅ Users and teachers created\n";

        // ========== STUDENT GROUPS ==========
        echo "Creating student groups...\n";

        // Groups for School 1
        $groupsSchool1 = [];
        $groupNames1 = ['6ème A', '6ème B', '5ème A', '5ème B', '4ème A', '4ème B', '3ème A', '3ème B'];

        foreach ($groupNames1 as $index => $groupName) {
            $group = new StudentGroup();
            $group->setName($groupName);
            $group->setSchool($school1);
            $group->setTeacher($index % 2 === 0 ? $teacher1 : $teacher2);
            $manager->persist($group);
            $groupsSchool1[] = $group;
        }

        // Groups for School 2
        $groupsSchool2 = [];
        $groupNames2 = ['CM1 A', 'CM1 B', 'CM2 A', 'CM2 B', '6ème', '5ème', '4ème', '3ème'];

        foreach ($groupNames2 as $index => $groupName) {
            $group = new StudentGroup();
            $group->setName($groupName);
            $group->setSchool($school2);
            $group->setTeacher($index % 2 === 0 ? $teacher3 : $teacher4);
            $manager->persist($group);
            $groupsSchool2[] = $group;
        }

        $manager->flush();
        echo "✅ Student groups created\n";

        // ========== STUDENTS ==========
        echo "Creating students...\n";

        $firstNames = ['Emma', 'Lucas', 'Chloé', 'Hugo', 'Léa', 'Louis', 'Manon', 'Jules', 'Camille', 'Nathan'];
        $lastNames = ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau'];
        $niveaux = ['6ème', '5ème', '4ème', '3ème', 'CM1', 'CM2'];

        // Create 8 students per group for School 1
        $studentCounter = 1;
        foreach ($groupsSchool1 as $group) {
            for ($i = 0; $i < 8; $i++) {
                $student = new Student();
                $student->setFirstName($firstNames[array_rand($firstNames)] . ' ' . $studentCounter);
                $student->setLastName($lastNames[array_rand($lastNames)]);
                $student->setDateOfBirth(new \DateTime('-' . rand(10, 14) . ' years'));
                $student->setNiveauScolaire($group->getName()); // Use group name as niveau
                $student->setStudentGroup($group);
                $student->setSchool($school1); // Set school directly
                $manager->persist($student);
                $studentCounter++;
            }
        }

        // Create 8 students per group for School 2
        foreach ($groupsSchool2 as $group) {
            for ($i = 0; $i < 8; $i++) {
                $student = new Student();
                $student->setFirstName($firstNames[array_rand($firstNames)] . ' ' . $studentCounter);
                $student->setLastName($lastNames[array_rand($lastNames)]);
                $student->setDateOfBirth(new \DateTime('-' . rand(8, 12) . ' years'));
                $student->setNiveauScolaire($group->getName()); // Use group name as niveau
                $student->setStudentGroup($group);
                $student->setSchool($school2); // Set school directly
                $manager->persist($student);
                $studentCounter++;
            }
        }

        $manager->flush();
        echo "✅ Students created ($studentCounter total)\n";

        // ========== SAMPLE SCHEDULES (Optional - for testing) ==========
        echo "Creating sample schedules...\n";

        // Create a few sample schedules for testing
        foreach ([$groupsSchool1[0], $groupsSchool2[0]] as $group) {
            // Math on Wednesday
            $scheduleMath = new Schedule();
            $scheduleMath->setStudentGroup($group);
            $scheduleMath->setSubject('Math');
            $scheduleMath->setDayOfWeek('Wednesday');
            $scheduleMath->setStartTime(\DateTime::createFromFormat('H:i', '14:00'));
            $scheduleMath->setEndTime(\DateTime::createFromFormat('H:i', '15:00'));
            $manager->persist($scheduleMath);

            // French on Thursday
            $scheduleFrench = new Schedule();
            $scheduleFrench->setStudentGroup($group);
            $scheduleFrench->setSubject('French');
            $scheduleFrench->setDayOfWeek('Thursday');
            $scheduleFrench->setStartTime(\DateTime::createFromFormat('H:i', '16:00'));
            $scheduleFrench->setEndTime(\DateTime::createFromFormat('H:i', '17:00'));
            $manager->persist($scheduleFrench);
        }

        $manager->flush();
        echo "✅ Sample schedules created\n";

        // ========== SAMPLE ATTENDANCE (for testing) ==========
        echo "Creating sample attendance...\n";

        // Get all students to create sample attendance
        $allStudents = $manager->getRepository(Student::class)->findAll();
        $statuses = ['present', 'absent', 'late', 'excused'];

        // Create sample attendance for yesterday
        $yesterday = new \DateTime('yesterday');

        foreach ($allStudents as $index => $student) {
            // Create attendance for every 5th student as sample
            if ($index % 5 === 0) {
                $attendance = new Attendance();
                $attendance->setStudent($student);
                $attendance->setStudentGroup($student->getStudentGroup()); // Set student group

                // Create a dummy schedule for the attendance
                $dummySchedule = new Schedule();
                $dummySchedule->setStudentGroup($student->getStudentGroup());
                $dummySchedule->setSubject($index % 2 === 0 ? 'Math' : 'French');
                $dummySchedule->setDayOfWeek($yesterday->format('l'));
                $dummySchedule->setStartTime(\DateTime::createFromFormat('H:i', '09:00'));
                $dummySchedule->setEndTime(\DateTime::createFromFormat('H:i', '10:00'));
                $manager->persist($dummySchedule);

                $attendance->setSchedule($dummySchedule);
                $attendance->setDate($yesterday);

                // 80% present, 20% other statuses
                $random = rand(1, 100);
                if ($random <= 80) {
                    $status = 'present';
                } elseif ($random <= 90) {
                    $status = 'late';
                } elseif ($random <= 95) {
                    $status = 'excused';
                } else {
                    $status = 'absent';
                }

                $attendance->setStatus($status);
                $manager->persist($attendance);
            }
        }

        $manager->flush();
        echo "✅ Sample attendance created\n";

        echo "\n🎉 Fixtures loaded successfully!\n";
        echo "========================================\n";
        echo "Admin login:\n";
        echo "  Username: admin\n";
        echo "  Password: admin123\n";
        echo "\nTeacher logins (password: teacher123):\n";
        echo "  School 1: marie.durand, jean.martin\n";
        echo "  School 2: sophie.leroy, pierre.duval\n";
        echo "\nDatabase stats:\n";
        echo "  Schools: 2\n";
        echo "  Student groups: " . (count($groupsSchool1) + count($groupsSchool2)) . "\n";
        echo "  Students: " . count($allStudents) . "\n";
        echo "  Teachers: 4\n";
        echo "========================================\n";
    }

    private function createTeacher(
        string $username,
        string $firstName,
        string $lastName,
        string $password,
        School $school,
        ObjectManager $manager
    ): Teacher {
        // Create User
        $user = new User();
        $user->setUsername($username);
        $user->setRoles(['ROLE_TEACHER']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $manager->persist($user);

        // Create Teacher
        $teacher = new Teacher();
        $teacher->setFirstName($firstName);
        $teacher->setLastName($lastName);
        $teacher->setPhone('0' . rand(1, 9) . rand(10, 99) . rand(10, 99) . rand(10, 99) . rand(10, 99));
        $teacher->setSchool($school);
        $teacher->setUser($user);
        $manager->persist($teacher);

        return $teacher;
    }
}