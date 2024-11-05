import { Module } from '@nestjs/common';
import { SmsModule } from './sms/sms.module';

@Module({
  imports: [SmsModule],
  exports: [SmsModule],
})
export class IntegrationsModule {}
