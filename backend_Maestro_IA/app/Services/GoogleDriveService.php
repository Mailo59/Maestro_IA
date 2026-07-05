<?php

declare(strict_types=1);

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GoogleDriveService
{
    private const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive';

    public function __construct(
        private readonly Client $http = new Client(),
    ) {
    }

    /**
     * @return array{drive_file_id: string, view_url: string}
     */
    public function uploadImage(UploadedFile $file): array
    {
        return $this->uploadFile($file);
    }

    /**
     * @return array{drive_file_id: string, view_url: string}
     */
    public function uploadFile(UploadedFile $file): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $folderId = $this->getRequiredConfig('folder_id');
            $fileName = $this->buildFileName($file);
            $mimeType = $file->getMimeType() ?: 'application/octet-stream';
            $fileContents = file_get_contents($file->getRealPath());

            if ($fileContents === false) {
                throw new RuntimeException('No se pudo leer el archivo temporal subido.');
            }

            $uploadedFile = $this->createDriveFile(
                accessToken: $accessToken,
                folderId: $folderId,
                fileName: $fileName,
                mimeType: $mimeType,
                fileContents: $fileContents,
            );

            $fileId = (string) ($uploadedFile['id'] ?? '');

            if ($fileId === '') {
                throw new RuntimeException('Google Drive no retorno un ID de archivo valido.');
            }

            $this->makeFilePublic(accessToken: $accessToken, fileId: $fileId);

            return [
                'drive_file_id' => $fileId,
                'view_url' => (string) (
                    $uploadedFile['webViewLink']
                    ?? sprintf('https://drive.google.com/file/d/%s/view?usp=sharing', $fileId)
                ),
            ];
        } catch (Throwable $exception) {
            Log::error('Error al subir archivo a Google Drive.', [
                'message' => $exception->getMessage(),
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
            ]);

            throw new RuntimeException(
                'No se pudo subir el archivo a Google Drive.',
                previous: $exception,
            );
        }
    }

    public function deleteFile(?string $fileId): void
    {
        if ($fileId === null || trim($fileId) === '') {
            return;
        }

        try {
            $accessToken = $this->getAccessToken();

            $this->http->delete("https://www.googleapis.com/drive/v3/files/{$fileId}", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo eliminar archivo de Google Drive.', [
                'file_id' => $fileId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function getAccessToken(): string
    {
        $refreshToken = (string) config('filesystems.disks.google.refresh_token');

        if ($refreshToken !== '') {
            return $this->getAccessTokenFromRefreshToken($refreshToken);
        }

        $credentials = new ServiceAccountCredentials(self::DRIVE_SCOPE, [
            'type' => 'service_account',
            'client_email' => $this->getRequiredConfig('client_email'),
            'private_key' => $this->getRequiredConfig('private_key'),
        ]);

        $token = $credentials->fetchAuthToken();
        $accessToken = (string) ($token['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('No se pudo obtener un access token para Google Drive.');
        }

        return $accessToken;
    }

    private function getAccessTokenFromRefreshToken(string $refreshToken): string
    {
        $response = $this->http->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id' => $this->getRequiredConfig('client_id'),
                'client_secret' => $this->getRequiredConfig('client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ],
        ]);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $accessToken = (string) ($payload['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('No se pudo obtener un access token con el refresh token de Google.');
        }

        return $accessToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function createDriveFile(
        string $accessToken,
        string $folderId,
        string $fileName,
        string $mimeType,
        string $fileContents,
    ): array {
        $boundary = 'maestro_ia_'.Str::random(32);
        $metadata = json_encode([
            'name' => $fileName,
            'parents' => [$folderId],
        ], JSON_THROW_ON_ERROR);

        $body = implode("\r\n", [
            "--{$boundary}",
            'Content-Type: application/json; charset=UTF-8',
            '',
            $metadata,
            "--{$boundary}",
            "Content-Type: {$mimeType}",
            '',
            $fileContents,
            "--{$boundary}--",
            '',
        ]);

        $response = $this->http->post('https://www.googleapis.com/upload/drive/v3/files', [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => "multipart/related; boundary={$boundary}",
            ],
            'query' => [
                'uploadType' => 'multipart',
                'fields' => 'id,name,webViewLink',
            ],
            'body' => $body,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function makeFilePublic(string $accessToken, string $fileId): void
    {
        $this->http->post("https://www.googleapis.com/drive/v3/files/{$fileId}/permissions", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/json',
            ],
            'query' => [
                'fields' => 'id',
            ],
            'json' => [
                'role' => 'reader',
                'type' => 'anyone',
            ],
        ]);
    }

    private function buildFileName(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg';

        return sprintf('%s.%s', (string) Str::uuid(), $extension);
    }

    private function getRequiredConfig(string $key): string
    {
        $value = (string) config("filesystems.disks.google.{$key}");

        if ($value === '') {
            throw new RuntimeException("Falta configurar filesystems.disks.google.{$key}.");
        }

        return $value;
    }
}
