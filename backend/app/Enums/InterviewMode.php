<?php

namespace App\Enums;

enum InterviewMode: string
{
    case InPerson = 'in_person';
    case Phone = 'phone';
    case Video = 'video';
}
