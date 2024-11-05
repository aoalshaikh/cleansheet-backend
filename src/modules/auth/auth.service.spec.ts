import { Test, TestingModule } from '@nestjs/testing';
import { AuthService } from './auth.service';
import { UsersService } from '../users/users.service';
import { JwtService } from '@nestjs/jwt';
import * as bcrypt from 'bcrypt';
import { AuthController } from './auth.controller';

describe('AuthService', () => {
  let service: AuthService;

  const mockUsersService = {
    findByEmail: jest.fn(),
  };

  const mockJwtService = {
    sign: jest.fn(() => 'test_token'),
  };

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        AuthService,
        { provide: UsersService, useValue: mockUsersService },
        { provide: JwtService, useValue: mockJwtService },
      ],
      controllers: [AuthController],
    }).compile();

    service = module.get<AuthService>(AuthService);
  });

  it('should validate user by email', async () => {
    const user = { id: 1, email: 'test@example.com', password: 'hashed_password' };
    mockUsersService.findByEmail.mockResolvedValue(user);
    // @ts-ignore
    jest.spyOn(bcrypt, 'compare').mockResolvedValue(true);

    const result = await service.validateUserByEmail(
      'test@example.com',
      'password',
    );
    expect(result).toEqual(user);
  });
});
