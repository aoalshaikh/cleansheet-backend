// src/modules/auth/auth.service.ts
import { Injectable } from '@nestjs/common';
import { UsersService } from '../users/users.service';
import * as bcrypt from 'bcrypt';
import { User } from '../users/user.entity';
import { JwtService } from '@nestjs/jwt';

@Injectable()
export class AuthService {
  constructor(
    private usersService: UsersService,
    private jwtService: JwtService,
  ) {}

  async validateUserByEmail(
    email: string,
    password: string,
  ): Promise<User | null> {
    const user = await this.usersService.findByEmail(email);
    if (
      user &&
      user.password &&
      (await bcrypt.compare(password, user.password))
    ) {
      return user;
    }
    return null;
  }

  async login(user: User) {
    const payload = { sub: user.id, roles: user.roles };
    return {
      access_token: this.jwtService.sign(payload),
    };
  }

  async findOrCreateUserByIdentifier(identifier: string): Promise<User> {
    let user: User;
    if (this.isPhoneNumber(identifier)) {
      user = await this.usersService.findByPhoneNumber(identifier);
      if (!user) {
        user = await this.usersService.create({ phoneNumber: identifier });
      }
    } else {
      user = await this.usersService.findByEmail(identifier);
      if (!user) {
        user = await this.usersService.create({ email: identifier });
      }
    }
    return user;
  }

  isPhoneNumber(identifier: string): boolean {
    return /^\+?[1-9]\d{1,14}$/.test(identifier);
  }
}
