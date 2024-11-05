import { Module } from '@nestjs/common';
import { OtpService } from './otp.service';
import { TypeOrmModule } from '@nestjs/typeorm';
import { Otp } from './otp.entity';
import { UsersModule } from '../users/users.module';
import { SmsModule } from '../integrations/sms/sms.module';
import { SMS_SERVICE } from '../integrations/sms/constants';
import { SmsService } from '../integrations/sms/sms.service';

@Module({
  imports: [TypeOrmModule.forFeature([Otp]), UsersModule, SmsModule],
  providers: [
    OtpService,
    {
      provide: SMS_SERVICE,
      useClass: SmsService, // or useFactory for dynamic providers
    }
  ],
  exports: [OtpService],
})
export class OtpModule {}
