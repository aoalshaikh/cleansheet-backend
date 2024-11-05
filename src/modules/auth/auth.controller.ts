import { Controller, Post, UseGuards, Request, Body, UnauthorizedException } from '@nestjs/common';
import { AuthService } from './auth.service';
import { LocalAuthGuard } from './guards/local-auth.guard';
import { OtpService } from '../otp/otp.service';
import { CreateUserDto } from '../users/dto/create-user.dto';
import { UsersService } from '../users/users.service';

@Controller('auth')
export class AuthController {
  constructor(
    private authService: AuthService,
    private otpService: OtpService,
    private usersService: UsersService,
  ) {}

  // Email + Password Login
  @UseGuards(LocalAuthGuard)
  @Post('login')
  async login(@Request() req) {
    return this.authService.login(req.user);
  }

  // Request OTP
  @Post('request-otp')
  async requestOtp(@Body('identifier') identifier: string) {
    await this.otpService.generateOtp(identifier);
    return { message: 'OTP sent' };
  }

  // Login with OTP
  @Post('login-otp')
  async loginOtp(@Body('identifier') identifier: string, @Body('otp') otp: string) {
    const isValid = await this.otpService.validateOtp(identifier, otp);
    if (!isValid) {
      throw new UnauthorizedException('Invalid OTP');
    }
    const user = await this.authService.findOrCreateUserByIdentifier(identifier);
    return this.authService.login(user);
  }

  @Post('register')
  async register(@Body() createUserDto: CreateUserDto) {
    const user = await this.usersService.create(createUserDto);
    return this.authService.login(user);
  }
}
