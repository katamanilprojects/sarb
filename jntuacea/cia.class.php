<?php
// cia.class.php - Unified Backward-Compatible CIA Facade
require_once("user.class.php");
require_once("logs.class.php");
require_once("courseoutcome.class.php");
require_once("assessmentstructure.class.php");
require_once("ciamarks.class.php");
require_once("seeassessment.class.php");
require_once("ciadataexchange.class.php");

/**
 * CIA (Continuous Internal Assessment) Facade Class
 * Integrates modular feature traits while preserving 100% backward compatibility
 * for all legacy script invocations ($ciaObj = new CIA()).
 */
class CIA extends User
{
    use CourseOutcomeTrait;
    use AssessmentStructureTrait;
    use CIAMarksTrait;
    use SEEAssessmentTrait;
    use CIADataExchangeTrait;

    protected $classname = "Faculty";

    public function __construct()
    {
        parent::__construct();
    }
}