<?php
declare(strict_types=1);
namespace College;
require_once __DIR__ . '/AssessmentPolicy.php';

final class ResultsPolicy
{
    public function __construct(private \mysqli $db) {}

    public function forSubject(int $subject): array
    {
        $profile = (new AssessmentPolicy($this->db))->forSubject($subject);
        if (!$profile['config']['grade_bands']) {
            $stmt = $this->db->prepare("SELECT setting_value FROM academic_settings WHERE reg_id=? AND setting_key='grade_bands_json'");
            $stmt->bind_param('i', $profile['context']['reg_id']); $stmt->execute();
            $settings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if (count($settings) > 1) { throw new \DomainException('Resolve duplicate regulation grade policies before importing.'); }
            $bands = $settings ? json_decode($settings[0]['setting_value'], true, 512, JSON_THROW_ON_ERROR) : [];
            if (!is_array($bands)) { throw new \DomainException('Invalid regulation grade-band configuration.'); }
            foreach ($bands as $band) {
                if (!is_array($band)) { throw new \DomainException('Invalid regulation grade-band configuration.'); }
                $profile['config']['grade_bands'][] = ['grade'=>$band['grade'] ?? '', 'points'=>$band['points'] ?? null,
                    'min_percent'=>$band['min_percent'] ?? $band['min'] ?? null,
                    'max_percent'=>$band['max_percent'] ?? $band['max'] ?? null];
            }
            AssessmentPolicy::validate($profile['config']);
        }
        return $profile;
    }
}
