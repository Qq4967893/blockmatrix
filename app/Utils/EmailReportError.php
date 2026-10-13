<?php

namespace App\Utils;

use Illuminate\Support\Facades\URL;
use Symfony\Component\Console\Input\ArgvInput;
use Scaffold\Email\Facades\Email;
use Scaffold\Framework\Utils\Format;

class EmailReportError
{
    /**
     * 发送异常邮件
     *
     * @param string $title 邮件标题
     * @param object $e     所抛出的异常
     */
    public static function sendExceptionEmail($title, $e)
    {
        $typeKey    = 'url';
        $errorClass = URL::full() ?? '';
        if (app()->runningInConsole()) {
            $typeKey    = 'script';
            $errorClass = (new ArgvInput())->getFirstArgument();
        }

        $title = Format::formatSubject($title);

        $content = "<b>env:</b>&nbsp;&nbsp;" . config('blockmatrix.app.env');
        $content .= "<br><b>time:</b>&nbsp;&nbsp;" . date('Y-m-d H:i:s');
        $content .= "<br><b>host:</b>&nbsp;&nbsp;" . gethostname();
        $content .= "<br><b>{$typeKey}:</b>&nbsp;&nbsp;" . $errorClass;
        $content .= "<br><b>err_code:</b>&nbsp;&nbsp;" . $e->getCode();
        $content .= "<br><b>err_file:</b>&nbsp;&nbsp;" . $e->getFile();
        $content .= "<br><b>err_line:</b>&nbsp;&nbsp;" . $e->getLine();
        $content .= "<br><b>err_message:</b>&nbsp;&nbsp;" . $e->getMessage();
        $content .= "<br><b>err_trace:</b>&nbsp;&nbsp;<pre>" . $e->getTraceAsString() ."</pre>";

        Email::sendDeveloperEmail($title, $content, md5($e->getCode(). $e->getFile(). $e->getLine()));
    }
}
