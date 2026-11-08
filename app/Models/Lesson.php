<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'course_id', 'course_section_id', 'title', 'content_type', 'content',
        'resource_path', 'lti_tool_id', 'scorm_package_id', 'external_launch_url',
        'sort_order', 'duration_minutes', 'status', 'publish_at', 'objectives', 'audience_class_ids', 'created_by',
    ];

    protected function casts(): array
    {
        return ['publish_at' => 'datetime', 'objectives' => 'array', 'audience_class_ids' => 'array'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function ltiTool(): BelongsTo
    {
        return $this->belongsTo(LtiTool::class);
    }

    public function scormPackage(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class);
    }

    public function isExternalLaunch(): bool
    {
        return in_array($this->content_type, ['lti', 'scorm'], true)
            || filled($this->external_launch_url)
            || filled($this->lti_tool_id)
            || filled($this->scorm_package_id);
    }

    /** Published and past its scheduled time (ignores the audience). */
    public function isLive(): bool
    {
        return $this->status === 'published' && ($this->publish_at === null || $this->publish_at->lte(now()));
    }
}
