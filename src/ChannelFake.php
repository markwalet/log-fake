<?php

declare(strict_types=1);

namespace TiMacDonald\Log;

use Closure;
use Stringable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;
use Psr\Log\LoggerInterface;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;

class ChannelFake implements LoggerInterface
{
    use LogHelpers;

    private string $name;

    private Dispatcher $dispatcher;

    /**
     * @var array<array{level: mixed, message: string, context: array<string, mixed>, channel: string}>
     */
    private array $logs = [];

    /**
     * @var array<mixed>
     */
    private array $context = [];

    private int $timesForgotten = 0;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function assertLogged(string $level, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->logged($level, $callback)->count() > 0,
            "An expected log with level [{$level}] was not logged in the [{$this->name}] channel."
        );
    }

    public function assertLoggedTimes(string $level, int $times, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            ($count = $this->logged($level, $callback)->count()) === $times,
            "A log with level [{$level}] was logged [{$count}] times instead of an expected [{$times}] times in the [{$this->name}] channel."
        );
    }

    public function assertNotLogged(string $level, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            ($count = $this->logged($level, $callback)->count()) === 0,
            "An unexpected log with level [{$level}] was logged [$count] times in the [{$this->name}] channel."
        );
    }

    public function assertNothingLogged(): void
    {
        PHPUnit::assertTrue($this->logs()->isEmpty(), "Found [{$this->logs()->count()}] logs in the [{$this->name}] channel. Expected to find [0].");
    }

    public function assertLoggedMessage(string $level, string $message): void
    {
        $this->assertLogged($level, static function (string $loggedMessage) use ($message): bool {
            return $loggedMessage === $message;
        });
    }

    public function assertForgotten()
    {
        PHPUnit::assertGreaterThan(0, $this->timesForgotten, "Expected the [{$this->name}] channel to be forgotten. It was not forgotten.");
    }

    public function assertNotForgotten()
    {
        PHPUnit::assertSame(0, $this->timesForgotten, "Expected the [{$this->name}] channel to not be forgotten. It was forgotten [{$this->timesForgotten}] times.");
    }

    public function dump(?string $level = null): ChannelFake
    {
        $this->logs()
            ->when($level !== null, function (Collection $logs) use ($level): Collection {
                return $logs->where('level', $level)->values();
            })
            ->dump();

        return $this;
    }

    public function dd(?string $level = null): never
    {
        $this->dump($level);

        exit(1);
    }

    public function write(string $level, string $message, array $context = []): void
    {
        $this->log($level, $message, $context);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => array_merge($this->context, $context),
            'times_channel_has_been_forgotten_at_time_of_writing_log' => $this->timesForgotten,
            'channel' => $this->name,
        ];
    }

    public function getLogger(): ChannelFake
    {
        return $this;
    }

    public function listen(Closure $callback): void
    {
        //
    }

    public function getEventDispatcher(): Dispatcher
    {
        return $this->dispatcher;
    }

    public function setEventDispatcher(Dispatcher $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function withContext(array $context = []): ChannelFake
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    public function withoutContext(): ChannelFake
    {
        $this->context = [];

        return $this;
    }

    public function logged(string $level, ?callable $callback = null): Collection
    {
        return $this->logs()
            ->where('level', $level)
            ->when(
                $callback !== null,
                fn ($collection) => $collection->filter(
                    fn ($log) => $callback($log['message'], $log['context'])
                )->values()
            );
    }

    public function logs(): Collection
    {
        return Collection::make($this->logs);
    }

    /**
     * @internal
     */
    public function forget()
    {
        $this->forgotten = true;
    }

    /**
     * @internal
     */
    public function hasBeenForgotten(): bool
    {
        return $this->forgotten;
    }

    public function dumpAll(?string $level = null): never
    {
        throw new RuntimeException('LogFake::dumpAll() should not be called from a channel.');
    }

    public function ddAll(string $level = null): never
    {
        throw new RuntimeException('`ddAll()` should not be called from a channel. Call it directly on `LogFake::ddAll()`.');
    }
}
