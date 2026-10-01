<?php

use App\Support\Uploads;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;


return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->string('financial_opening_password_hash')->nullable();
            $table->unsignedTinyInteger('financial_password_attempts')->default(0);
            $table->timestamp('financial_password_locked_until')->nullable();
            $table->string('financial_opening_method', 30)->nullable();
            $table->text('financial_opening_exception_reason')->nullable();
            $table->timestamp('proposal_file_encrypted_at')->nullable();
        });
        Schema::table('bid_documents', function (Blueprint $table) {
            $table->timestamp('encrypted_at')->nullable();
        });

        // Encrypt existing financial uploads in place so the new protected
        // readers can continue serving historical bids without changing paths.
        $encryptedPaths = [];
        foreach (DB::table('bid_documents')->where('component', 'financial')->whereNull('encrypted_at')->get(['id', 'file_path']) as $document) {
            $path = (string) $document->file_path;
            $encryptedAt = $encryptedPaths[$path] ?? $this->encryptAtRest($path);
            if ($encryptedAt !== null) {
                $encryptedPaths[$path] = $encryptedAt;
                DB::table('bid_documents')->where('id', $document->id)->update(['encrypted_at' => $encryptedAt]);
            }
        }
        foreach (DB::table('bids')->whereNotNull('proposal_file')->whereNull('proposal_file_encrypted_at')->get(['id', 'proposal_file']) as $bid) {
            $path = (string) $bid->proposal_file;
            $encryptedAt = $encryptedPaths[$path] ?? $this->encryptAtRest($path);
            if ($encryptedAt !== null) {
                $encryptedPaths[$path] = $encryptedAt;
                DB::table('bids')->where('id', $bid->id)->update(['proposal_file_encrypted_at' => $encryptedAt]);
            }
        }
    }

    public function down(): void
    {
        $decryptedPaths = [];
        foreach (DB::table('bid_documents')->whereNotNull('encrypted_at')->get(['id', 'file_path']) as $document) {
            $path = (string) $document->file_path;
            $this->decryptAtRestOnce($path, $decryptedPaths);
        }
        foreach (DB::table('bids')->whereNotNull('proposal_file_encrypted_at')->whereNotNull('proposal_file')->get(['id', 'proposal_file']) as $bid) {
            $this->decryptAtRestOnce((string) $bid->proposal_file, $decryptedPaths);
        }

        Schema::table('bid_documents', fn (Blueprint $table) => $table->dropColumn('encrypted_at'));
        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn([
                'financial_opening_password_hash', 'financial_password_attempts',
                'financial_password_locked_until', 'financial_opening_method',
                'financial_opening_exception_reason', 'proposal_file_encrypted_at',
            ]);
        });
    }

    private function encryptAtRest(string $path): ?string
    {
        $contents = $this->readStoredPath($path);
        if ($contents === null) return null;

        $this->writeStoredPath($path, Crypt::encryptString($contents));
        return now();
    }

    private function decryptAtRestOnce(string $path, array &$decryptedPaths): void
    {
        if (array_key_exists($path, $decryptedPaths)) return;
        $payload = $this->readStoredPath($path);
        if ($payload === null) throw new RuntimeException('Cannot roll back financial file encryption; stored file is missing.');

        $this->writeStoredPath($path, Crypt::decryptString($payload));
        $decryptedPaths[$path] = true;
    }

    private function readStoredPath(string $path): ?string
    {
        if (Uploads::isLegacyPublicPath($path)) {
            $absolute = public_path($path);
            return is_file($absolute) ? file_get_contents($absolute) : null;
        }

        $disk = Storage::disk(Uploads::diskName());
        return $disk->exists($path) ? $disk->get($path) : null;
    }

    private function writeStoredPath(string $path, string $contents): void
    {
        if (Uploads::isLegacyPublicPath($path)) {
            $absolute = public_path($path);
            if (file_put_contents($absolute, $contents) === false) throw new RuntimeException('Cannot update stored financial bid file.');
            return;
        }

        if (! Storage::disk(Uploads::diskName())->put($path, $contents)) throw new RuntimeException('Cannot update stored financial bid file.');
    }
};