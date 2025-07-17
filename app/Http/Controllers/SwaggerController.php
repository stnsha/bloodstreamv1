<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0",
 *     title="Blood Stream v1 API",
 *     description="",
 *     @OA\Contact(name="Digital Innovation")
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description=L5_SWAGGER_CONST_DESCRIPTION
 * )
 */
class SwaggerController extends Controller
{
    // This controller is only for OpenAPI annotations
}
