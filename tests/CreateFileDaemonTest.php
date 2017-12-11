<?php
namespace tests;

use Symfony\Component\Process\Process;
use yii\console\Controller;
use Yii;

class CreateFileDaemonTest extends \PHPUnit_Framework_TestCase
{
    protected $process;

    public function testRun()
    {
        $process = $this->startProcess('php tests/yii create-file');

        sleep(1);

        $this->assertTrue($process->isRunning());
        $process->stop(3);
        sleep(1);
        $this->assertEquals($process->wait(), Controller::EXIT_CODE_NORMAL);

        // var_dump($process);
        // var_dump($process->stop());

        // sleep(1);
        // $process->stop(3);
        // var_dump($process);
        // $this->assertTrue($process->isTerminated());
        // var_dump(glob(Yii::getAlias('@runtime/daemons') . '/*.pid'));
        // $this->assertEmpty(glob(Yii::getAlias('@runtime/daemons') . '/*.pid'));
    }

    protected function tearDown()
    {
        foreach (glob(Yii::getAlias("@runtime/*.txt")) as $fileName) {
            unlink($fileName);
        }

        parent::tearDown();
    }

    /**
     * @param string $cmd
     */
    protected function startProcess($cmd)
    {
        $process = new Process($this->prepareCmd($cmd));
        $process->start();
        return $process;
    }

        /**
     * @param string $cmd
     * @return string
     */
    private function prepareCmd($cmd)
    {
        return strtr($cmd, [
            'php' => PHP_BINARY,
        ]);
    }
}