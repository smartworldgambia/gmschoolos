<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 - portals and communication: account lifecycle, invitations for every kind of user, school and class
 * announcements, internal messaging with reporting and moderation, notification preferences, delivery history and SMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable();
            $table->unsignedBigInteger('suspended_by')->nullable();
            $table->string('suspension_reason', 255)->nullable();
            $table->timestamp('access_revoked_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->index(['school_id', 'suspended_at']);
        });

        // One invitation table for every kind of user: staff (existing), student and guardian.
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->string('kind', 12)->default('staff');            // staff | student | guardian
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('guardian_id')->nullable();
            $table->json('class_subject_ids')->nullable();           // teachers: allocations to assign on acceptance
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 190);
            $table->text('body');
            $table->string('audience', 16);                          // school | staff | teachers | students | guardians | classes
            $table->json('class_ids')->nullable();
            $table->boolean('include_students')->default(true);      // classes audience: learners
            $table->boolean('include_guardians')->default(true);     // classes audience: guardians
            $table->json('channels')->nullable();                    // in_app is always on; email / sms are opt-in per announcement
            $table->string('status', 12)->default('draft');          // draft | scheduled | published | cancelled
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
            $table->index(['school_id', 'status', 'publish_at']);
        });

        Schema::create('announcement_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12)->default('direct');           // direct | class_group
            $table->string('subject', 190)->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();       // which child this is about
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('replies_allowed')->default(true);       // class groups: may learners / guardians answer?
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'last_message_at']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member');           // owner | member
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('muted')->default(false);
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedBigInteger('hidden_by')->nullable();
            $table->string('hidden_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('path');                                   // private disk, random name
            $table->string('original_name', 190);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('message_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->string('status', 10)->default('open');           // open | dismissed | actioned
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->string('resolution', 500)->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 24);
            $table->string('channel', 10);                            // in_app | email | sms
            $table->boolean('enabled');
            $table->timestamps();
            $table->unique(['user_id', 'category', 'channel']);
        });

        // History of in-app and e-mail deliveries. "sent" means the mail transport accepted it; it is never "delivered".
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notification_id', 40)->nullable();
            $table->string('notification_type', 120);
            $table->string('category', 24)->nullable();
            $table->string('channel', 10);                            // in_app | email
            $table->string('status', 10);                             // sent | logged | failed
            $table->string('summary', 190)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
            $table->index(['school_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('locale', 5);
            $table->string('body', 480);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'key', 'locale']);
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_phone', 24);                           // E.164
            $table->string('template_key', 40)->nullable();
            $table->string('category', 24)->nullable();
            $table->text('body');
            $table->unsignedSmallInteger('segments')->default(1);
            $table->string('status', 12)->default('queued');          // queued | sent | delivered | failed | logged
            $table->string('provider', 24)->nullable();
            $table->string('provider_ref', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->decimal('cost_amount', 10, 4)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status', 'created_at']);
            $table->index(['provider', 'provider_ref']);
        });
    }

    public function down(): void
    {
        foreach (['sms_messages', 'sms_templates', 'notification_deliveries', 'notification_preferences', 'message_reports',
            'message_attachments', 'messages', 'conversation_participants', 'conversations', 'announcement_recipients', 'announcements'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->dropColumn(['kind', 'student_id', 'guardian_id', 'class_subject_ids']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'suspended_at']);
            $table->dropColumn(['suspended_at', 'suspended_by', 'suspension_reason', 'access_revoked_at', 'last_login_at']);
        });
    }
};
