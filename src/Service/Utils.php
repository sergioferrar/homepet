<?php

namespace App\Service;

class Utils
{


    private $status;
    private $message;
    private $error;
    private $data;

    public function info($message)
    {
        $this->message = $message;
        $this->status = 'info';
        $this->error = false;
        return $this;
    }

    public function alert($message)
    {
        $this->message = $message;
        $this->status = 'warning';
        $this->error = false;
        return $this;
    }

    public function success($message)
    {
        $this->message = $message;
        $this->status = 'success';
        $this->error = false;
        return $this;
    }

    public function danger($message)
    {
        $this->message = $message;
        $this->status = 'danger';
        $this->error = true;
        return $this;
    }

    public function data($data)
    {
        $this->data = $data;
        return $this;
    }

    public function message()
    {

        return [
            'message' => $this->message,
            'status' => $this->status,
            'error' => $this->error,
            'data' => $this->data,
        ];
    }

}