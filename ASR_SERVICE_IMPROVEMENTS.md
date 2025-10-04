# AsrService Improvements

## Issues Found in Original Code

### 1. **Security & Validation**
- ❌ No file type validation
- ❌ No file size limits
- ❌ No MIME type checking
- ❌ Could accept malicious files

### 2. **Error Handling**
- ❌ Silent error suppression with `@unlink()`
- ❌ Generic RuntimeException instead of specific exceptions
- ❌ No file existence/readability checks
- ❌ Missing validation of response structure

### 3. **Configuration**
- ❌ Hardcoded values (`'/transcribe'`, timeout)
- ❌ No retry mechanism for transient failures
- ❌ Configuration not externalized

### 4. **Resource Management**
- ❌ `file_get_contents()` loads entire file into memory
- ❌ No cleanup logging
- ❌ Silent cleanup failures

### 5. **Type Safety**
- ❌ Array return type instead of proper DTO
- ❌ No validation of response structure
- ❌ Loose type handling

### 6. **Testing & Maintainability**
- ❌ Tight coupling to facades
- ❌ No interface for dependency injection
- ❌ Hard to mock for testing

## Improvements Made

### New Files Created

#### 1. **TranscriptionResult DTO** (`app/DataTransferObjects/TranscriptionResult.php`)
```php
readonly class TranscriptionResult
{
    public function __construct(
        public string $text,
        public ?int $modelTimeMs,
        public float $endToEndMs,
        public bool $success = true,
        public ?string $error = null,
    ) {}
}
```

**Benefits:**
- ✅ Type-safe return values
- ✅ Immutable data structure
- ✅ Self-documenting API
- ✅ Easy to serialize/deserialize

#### 2. **Custom Exception** (`app/Exceptions/AsrServiceException.php`)
```php
class AsrServiceException extends \RuntimeException
{
    public static function serviceUnavailable(int $statusCode, string $body): self
    public static function invalidResponse(string $reason): self
    public static function fileError(string $reason, ?\Throwable $previous = null): self
}
```

**Benefits:**
- ✅ Specific exception types for different failures
- ✅ Better error context
- ✅ Easier to catch and handle specific errors
- ✅ Static factory methods for clarity

#### 3. **Configuration** (`config/asr.php`)
```php
return [
    'url' => env('ASR_URL', 'http://localhost:5000'),
    'route' => env('ASR_ROUTE', '/transcribe'),
    'timeout' => env('ASR_TIMEOUT', 60),
    'max_file_size' => env('ASR_MAX_FILE_SIZE', 10 * 1024 * 1024),
    'allowed_extensions' => ['wav', 'mp3', 'ogg', 'webm', 'flac', 'm4a'],
    'allowed_mime_types' => [...],
    'retry' => [...],
];
```

**Benefits:**
- ✅ Environment-specific configuration
- ✅ Easy to change without code modification
- ✅ Centralized settings
- ✅ Default values for development

#### 4. **Improved Service** (`app/Services/AsrService.improved.php`)

### Key Improvements

#### **Security & Validation**
```php
private function validateFile(UploadedFile $file): void
{
    // File size check
    if ($file->getSize() > $this->maxFileSize) { ... }
    
    // Extension whitelist
    if (!in_array($extension, $this->allowedExtensions, true)) { ... }
    
    // MIME type validation
    if (!in_array($mimeType, $this->allowedMimeTypes, true)) { ... }
    
    // File validity check
    if (!$file->isValid()) { ... }
}
```

#### **Better Error Handling**
```php
// File existence check
if (!file_exists($fullPath)) {
    throw AsrServiceException::fileError("File not found: {$fullPath}");
}

// Readability check
if (!is_readable($fullPath)) {
    throw AsrServiceException::fileError("File not readable: {$fullPath}");
}

// Response validation
if (!isset($json['text'])) {
    throw AsrServiceException::invalidResponse('Missing "text" field');
}
```

#### **Retry Mechanism**
```php
private function makeRequest(string $fullPath, string $url)
{
    $attempt = 0;
    $maxAttempts = $this->retryEnabled ? $this->retryTimes + 1 : 1;
    
    while ($attempt < $maxAttempts) {
        // Retry on server errors (5xx)
        // Don't retry on client errors (4xx)
        // Exponential backoff possible
    }
}
```

#### **Better Logging**
```php
// File storage logging
Log::info('ASR: file stored', [
    'path' => $path,
    'size' => $file->getSize(),
    'mime' => $file->getMimeType(),
]);

// Success logging
Log::info('ASR: transcription complete', [
    'text_length' => mb_strlen($text),
    'text_preview' => mb_substr($text, 0, 80),
    'model_ms' => $modelTimeMs,
    'e2e_ms' => round($e2eMs, 1),
]);

// Cleanup warnings
Log::warning('ASR: failed to delete temp file', ['path' => $fullPath]);
```

#### **Proper Cleanup**
```php
private function cleanupFile(string $fullPath, string $storagePath): void
{
    try {
        // Try direct file deletion
        if (file_exists($fullPath) && !unlink($fullPath)) {
            Log::warning('ASR: failed to delete temp file', ['path' => $fullPath]);
        }
        
        // Also try via Storage facade
        if (Storage::disk('local')->exists($storagePath)) {
            Storage::disk('local')->delete($storagePath);
        }
    } catch (\Throwable $e) {
        Log::error('ASR: cleanup error', [
            'path' => $fullPath,
            'error' => $e->getMessage(),
        ]);
    }
}
```

## Migration Guide

### 1. Add Configuration File
```bash
# Copy config/asr.php to your config directory
```

### 2. Update Environment Variables
```env
ASR_URL=http://localhost:5000
ASR_ROUTE=/transcribe
ASR_TIMEOUT=60
ASR_MAX_FILE_SIZE=10485760  # 10MB
ASR_RETRY_ENABLED=true
ASR_RETRY_TIMES=2
ASR_RETRY_SLEEP_MS=100
```

### 3. Update Controllers
```php
// OLD
$result = $asrService->transcribeUploadedFile($file);
$text = $result['text'];

// NEW
$result = $asrService->transcribeUploadedFile($file);
$text = $result->text;  // Type-safe property access
```

### 4. Update Exception Handling
```php
// OLD
try {
    $result = $asrService->transcribeUploadedFile($file);
} catch (\RuntimeException $e) {
    // Generic error handling
}

// NEW
try {
    $result = $asrService->transcribeUploadedFile($file);
} catch (AsrServiceException $e) {
    if ($e->statusCode >= 500) {
        // Server error - may retry later
    } else {
        // Client error - don't retry
    }
}
```

## Testing Improvements

### Unit Tests
```php
// With improved service, you can mock:
// - Http facade
// - Storage facade
// - Config values

public function test_validates_file_size()
{
    Config::set('asr.max_file_size', 1024);
    
    $file = UploadedFile::fake()->create('audio.wav', 2048);
    
    $this->expectException(AsrServiceException::class);
    $this->expectExceptionMessage('exceeds maximum allowed size');
    
    $service = new AsrService();
    $service->transcribeUploadedFile($file);
}
```

## Performance Considerations

### Memory Usage
The improved service still uses `file_get_contents()` for HTTP upload. For very large files, consider:
- Streaming uploads
- Chunked processing
- Background job processing

### Suggested Enhancement
```php
// For large files, use stream
private function makeRequest(string $fullPath, string $url)
{
    return Http::timeout($this->timeoutSeconds)
        ->attach('audio', fopen($fullPath, 'r'), basename($fullPath))
        ->post($url);
}
```

## Monitoring & Metrics

Consider adding:
```php
// Track success rate
Metrics::increment('asr.transcriptions.total');
Metrics::increment('asr.transcriptions.success');

// Track performance
Metrics::timing('asr.transcription.duration_ms', $e2eMs);

// Track file sizes
Metrics::histogram('asr.file.size_bytes', $file->getSize());
```

## Summary

| Aspect | Before | After |
|--------|--------|-------|
| Type Safety | Array return | DTO return |
| Validation | None | File size, type, MIME |
| Error Handling | Generic exception | Custom exceptions |
| Configuration | Hardcoded | Config file |
| Retry Logic | No | Yes (configurable) |
| Logging | Basic | Comprehensive |
| Cleanup | Silent (@) | Logged failures |
| Security | Weak | Strong validation |
| Testing | Hard | Easier with DI |

## Files to Replace

1. Replace `app/Services/AsrService.php` with `AsrService.improved.php`
2. Add `app/DataTransferObjects/TranscriptionResult.php`
3. Add `app/Exceptions/AsrServiceException.php`
4. Add `config/asr.php`
5. Update controllers that use AsrService
