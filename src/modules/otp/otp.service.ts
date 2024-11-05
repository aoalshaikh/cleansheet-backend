import { Inject, Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Otp } from './otp.entity';
import { Repository } from 'typeorm';
import { ISmsService } from '../integrations/sms/interfaces/sms-service.interface';
import { SMS_SERVICE } from '../integrations/sms/constants';

@Injectable()
export class OtpService {
  constructor(
    @InjectRepository(Otp) private otpRepository: Repository<Otp>,
    @Inject(SMS_SERVICE) private smsService: ISmsService,
  ) {}

  async generateOtp(identifier: string): Promise<void> {
    const otpCode = Math.floor(100000 + Math.random() * 900000).toString();
    const expiresAt = new Date(Date.now() + 5 * 60 * 1000);

    const otp = this.otpRepository.create({
      identifier,
      otp: otpCode,
      expiresAt,
    });

    await this.otpRepository.save(otp);

    if (this.isPhoneNumber(identifier)) {
      const message = `Your OTP code is ${otpCode}`;
      await this.smsService.sendSms(identifier, message);
    } else {
      // Implement email sending logic here
    }
  }

  async validateOtp(identifier: string, otpCode: string): Promise<boolean> {
    const otp = await this.otpRepository.findOne({
      where: { identifier, otp: otpCode },
      order: { createdAt: 'DESC' },
    });

    if (otp && otp.expiresAt > new Date()) {
      await this.otpRepository.delete(otp.id);
      return true;
    }
    return false;
  }

  isPhoneNumber(identifier: string): boolean {
    return /^\+?[1-9]\d{1,14}$/.test(identifier);
  }
}
