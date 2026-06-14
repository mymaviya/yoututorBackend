<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionTypeMaster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Class10EnglishBloomTestQuestionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $gradeId = 10;
            $subjectId = 138;
            $createdBy = 1;

            $types = [
                ['name' => 'MCQ', 'slug' => 'mcq'],
                ['name' => 'Short Answer', 'slug' => 'short'],
                ['name' => 'Long Answer', 'slug' => 'long'],
                ['name' => 'Extract Based', 'slug' => 'extract_based'],
                ['name' => 'Formal Letter', 'slug' => 'formal_letter'],
                ['name' => 'Analytical Paragraph', 'slug' => 'analytical_paragraph'],
            ];

            foreach ($types as $type) {
                QuestionTypeMaster::firstOrCreate(
                    ['slug' => $type['slug']],
                    [
                        'name' => $type['name'],
                        'is_active' => 1,
                    ]
                );
            }

            $typeIds = QuestionTypeMaster::whereIn('slug', collect($types)->pluck('slug'))
                ->pluck('id', 'slug');

            $questions = [];

            $this->addQuestions($questions, $gradeId, $subjectId, $typeIds['mcq'], 'mcq', 20, [
                'remember' => 8,
                'understand' => 4,
                'apply' => 4,
                'analyze' => 4,
            ]);

            $this->addQuestions($questions, $gradeId, $subjectId, $typeIds['short'], 'short', 11, [
                'remember' => 4,
                'understand' => 3,
                'apply' => 2,
                'analyze' => 2,
            ]);

            $this->addQuestions($questions, $gradeId, $subjectId, $typeIds['extract_based'], 'extract_based', 2, [
                'remember' => 1,
                'analyze' => 1,
            ]);

            $this->addQuestions($questions, $gradeId, $subjectId, $typeIds['formal_letter'], 'formal_letter', 1, [
                'apply' => 1,
            ]);

            $this->addQuestions($questions, $gradeId, $subjectId, $typeIds['analytical_paragraph'], 'analytical_paragraph', 1, [
                'analyze' => 1,
            ]);

            $this->addQuestions($questions, $gradeId, $subjectId, $typeIds['long'], 'long', 2, [
                'understand' => 1,
                'apply' => 1,
            ]);

            foreach ($questions as $question) {
                Question::create([
                    'grade_id' => $question['grade_id'],
                    'stream_id' => null,
                    'subject_id' => $question['subject_id'],
                    'lesson_id' => null,
                    'question_type_master_id' => $question['question_type_master_id'],
                    'question' => $question['question'],
                    'difficulty' => 'medium',
                    'bloom_level' => $question['bloom_level'],
                    'marks' => $question['marks'],
                    'answer' => $question['answer'],
                    'status' => 'approved',
                    'approved_by' => $createdBy,
                    'approved_at' => now(),
                    'is_active' => 1,
                    'created_by' => $createdBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function addQuestions(
        array &$questions,
        int $gradeId,
        int $subjectId,
        int $typeId,
        string $typeSlug,
        int $total,
        array $bloomDistribution
    ): void {
        $counter = 1;

        foreach ($bloomDistribution as $bloomLevel => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $questions[] = [
                    'grade_id' => $gradeId,
                    'subject_id' => $subjectId,
                    'question_type_master_id' => $typeId,
                    'bloom_level' => $bloomLevel,
                    'marks' => $this->marksForType($typeSlug),
                    'question' => $this->questionText($typeSlug, $bloomLevel, $counter),
                    'answer' => $this->answerText($typeSlug, $bloomLevel, $counter),
                ];

                $counter++;
            }
        }
    }

    private function marksForType(string $typeSlug): int
    {
        return match ($typeSlug) {
            'mcq' => 1,
            'short' => 3,
            'extract_based' => 5,
            'formal_letter' => 5,
            'analytical_paragraph' => 5,
            'long' => 6,
            default => 1,
        };
    }

    private function questionText(string $typeSlug, string $bloomLevel, int $number): string
    {
        $label = ucfirst(str_replace('_', ' ', $typeSlug));
        $bloom = ucfirst($bloomLevel);

        return "<p>Class 10 English {$label} {$bloom} Test Question {$number}</p>";
    }

    private function answerText(string $typeSlug, string $bloomLevel, int $number): string
    {
        $label = ucfirst(str_replace('_', ' ', $typeSlug));
        $bloom = ucfirst($bloomLevel);

        return "Sample answer for Class 10 English {$label} {$bloom} Test Question {$number}.";
    }
}